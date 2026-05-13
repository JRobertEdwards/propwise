import sys
from datetime import datetime
import pandas as pd
from tqdm import tqdm
import db
from config import CHUNK_SIZE

# Land Registry Price Paid CSV has no header — fields by position
LR_COLUMNS = [
    'transaction_id', 'price', 'sale_date', 'postcode', 'property_type',
    'new_build', 'estate_type', 'paon', 'saon', 'street', 'locality',
    'town_city', 'district', 'county', 'ppd_category', 'record_status',
]


def parse_price(value: str) -> int | None:
    try:
        return int(value)
    except (ValueError, TypeError):
        return None


def parse_date(value: str) -> str | None:
    for fmt in ('%Y-%m-%d %H:%M', '%Y-%m-%d'):
        try:
            return datetime.strptime(value.strip(), fmt).date().isoformat()
        except (ValueError, AttributeError):
            continue
    return None


def parse_new_build(value: str) -> bool:
    return str(value).strip().upper() == 'Y'


def normalise_postcode(postcode: str) -> str:
    return postcode.strip().upper().replace(' ', '')


def transform_row(row: dict) -> tuple | None:
    price = parse_price(row.get('price'))
    sale_date = parse_date(row.get('sale_date', ''))
    postcode = normalise_postcode(row.get('postcode', ''))

    if not price or not sale_date or not postcode:
        return None

    # Skip deletions and category B (bulk) transactions
    if row.get('record_status') == 'D' or row.get('ppd_category') == 'B':
        return None

    return (
        row.get('transaction_id', '').strip('{}'),
        price,
        sale_date,
        postcode,
        row.get('property_type', '').strip(),
        parse_new_build(row.get('new_build')),
        row.get('estate_type', '').strip(),
        row.get('paon', '').strip(),
        row.get('saon', '').strip() or None,
        row.get('street', '').strip() or None,
        row.get('locality', '').strip() or None,
        row.get('town_city', '').strip() or None,
        row.get('district', '').strip() or None,
        row.get('county', '').strip() or None,
    )


def run(filepath: str) -> None:
    conn = db.connect()
    sql = """
        INSERT INTO property_sales (
            transaction_id, price, sale_date, postcode, property_type,
            new_build, estate_type, paon, saon, street, locality,
            town_city, district, county
        ) VALUES %s
        ON CONFLICT (transaction_id) DO NOTHING
    """

    for chunk in tqdm(pd.read_csv(filepath, header=None, names=LR_COLUMNS, chunksize=CHUNK_SIZE, dtype=str), desc="Sales"):
        rows = [r for r in (transform_row(row) for row in chunk.to_dict('records')) if r]
        if rows:
            db.bulk_insert(conn, sql, rows)

    populate_locations(conn)
    conn.close()


def populate_locations(conn) -> None:
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE property_sales ps
            SET location = ST_SetSRID(ST_MakePoint(p.longitude, p.latitude), 4326)
            FROM postcodes p
            WHERE ps.postcode = p.postcode
              AND ps.location IS NULL
        """)
    conn.commit()


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python import_land_registry.py <path-to-lr-csv>")
        sys.exit(1)
    run(sys.argv[1])
