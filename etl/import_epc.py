import re
import sys
import pandas as pd
from tqdm import tqdm
import db
from config import CHUNK_SIZE

EPC_COLUMNS = [
    'LMK_KEY', 'ADDRESS1', 'ADDRESS2', 'ADDRESS3', 'POSTCODE',
    'PROPERTY_TYPE', 'BUILT_FORM', 'INSPECTION_DATE',
    'TOTAL_FLOOR_AREA', 'NUMBER_HABITABLE_ROOMS',
    'CURRENT_ENERGY_RATING', 'CONSTRUCTION_AGE_BAND',
]


def normalise_postcode(postcode: str) -> str:
    return postcode.strip().upper().replace(' ', '')


def normalise_address(address1: str, address2: str, postcode: str) -> str:
    parts = [address1, address2]
    combined = ' '.join(p.strip() for p in parts if p and str(p).strip().lower() != 'nan')
    normalised = re.sub(r'[^a-z0-9 ]', '', combined.lower())
    normalised = re.sub(r'\s+', ' ', normalised).strip()
    return normalised


def parse_floor_area(value) -> float | None:
    try:
        return float(value)
    except (ValueError, TypeError):
        return None


def parse_rooms(value) -> int | None:
    try:
        return int(float(value))
    except (ValueError, TypeError):
        return None


def transform_row(row: dict) -> tuple | None:
    lmk_key = str(row.get('LMK_KEY', '')).strip()
    postcode = normalise_postcode(str(row.get('POSTCODE', '')))

    if not lmk_key or not postcode:
        return None

    address1 = str(row.get('ADDRESS1', '') or '')
    address2 = str(row.get('ADDRESS2', '') or '')

    return (
        lmk_key,
        address1.strip() or None,
        address2.strip() or None,
        str(row.get('ADDRESS3', '') or '').strip() or None,
        postcode,
        str(row.get('PROPERTY_TYPE', '') or '').strip() or None,
        str(row.get('BUILT_FORM', '') or '').strip() or None,
        str(row.get('INSPECTION_DATE', '') or '').strip() or None,
        parse_floor_area(row.get('TOTAL_FLOOR_AREA')),
        parse_rooms(row.get('NUMBER_HABITABLE_ROOMS')),
        str(row.get('CURRENT_ENERGY_RATING', '') or '').strip()[:1] or None,
        str(row.get('CONSTRUCTION_AGE_BAND', '') or '').strip() or None,
        normalise_address(address1, address2, postcode),
    )


def run(filepath: str) -> None:
    conn = db.connect()
    sql = """
        INSERT INTO epc_certificates (
            lmk_key, address1, address2, address3, postcode,
            property_type, built_form, inspection_date,
            total_floor_area, number_habitable_rooms,
            current_energy_rating, construction_age_band, address_normalized
        ) VALUES %s
        ON CONFLICT (lmk_key) DO NOTHING
    """

    for chunk in tqdm(
        pd.read_csv(filepath, usecols=EPC_COLUMNS, chunksize=CHUNK_SIZE, dtype=str, low_memory=False),
        desc="EPC"
    ):
        rows = [r for r in (transform_row(row) for row in chunk.to_dict('records')) if r]
        if rows:
            db.bulk_insert(conn, sql, rows)

    conn.close()


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python import_epc.py <path-to-epc-csv>")
        sys.exit(1)
    run(sys.argv[1])
