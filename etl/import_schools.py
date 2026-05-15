import sys
import csv
import db
from pyproj import Transformer
from tqdm import tqdm
from config import CHUNK_SIZE

_transformer = Transformer.from_crs("EPSG:27700", "EPSG:4326", always_xy=False)


def convert_bng_to_wgs84(easting: float, northing: float) -> tuple[float, float]:
    lat, lng = _transformer.transform(easting, northing)
    return round(lat, 7), round(lng, 7)


def parse_row(row: dict) -> tuple | None:
    try:
        if row.get('EstablishmentStatus (name)', '').strip() != 'Open':
            return None

        urn = int(row['URN'])
        name = row['EstablishmentName'].strip()
        if not name:
            return None

        school_type = row.get('TypeOfEstablishment (name)', '').strip() or None
        phase = row.get('PhaseOfEducation (name)', '').strip() or None
        postcode = row.get('Postcode', '').strip().upper().replace(' ', '') or None

        easting_raw = row.get('Easting', '').strip()
        northing_raw = row.get('Northing', '').strip()

        if not easting_raw or not northing_raw:
            return None

        easting = float(easting_raw)
        northing = float(northing_raw)

        if easting == 0 or northing == 0:
            return None

        lat, lng = convert_bng_to_wgs84(easting, northing)

        return (urn, name, school_type, phase, postcode, lat, lng, f'SRID=4326;POINT({lng} {lat})')
    except (ValueError, KeyError):
        return None


def run(filepath: str) -> None:
    conn = db.connect()
    sql = """
        INSERT INTO schools (urn, name, type, phase, postcode, latitude, longitude, location)
        VALUES %s
        ON CONFLICT (urn) DO UPDATE SET
            name = EXCLUDED.name,
            type = EXCLUDED.type,
            phase = EXCLUDED.phase,
            postcode = EXCLUDED.postcode,
            latitude = EXCLUDED.latitude,
            longitude = EXCLUDED.longitude,
            location = EXCLUDED.location
    """

    batch = []
    total = 0

    with open(filepath, encoding='cp1252') as f:
        reader = csv.DictReader(f)
        for row in tqdm(reader, desc="Schools"):
            parsed = parse_row(row)
            if parsed:
                batch.append(parsed)
                if len(batch) >= CHUNK_SIZE:
                    db.bulk_insert(conn, sql, batch)
                    total += len(batch)
                    batch = []

    if batch:
        db.bulk_insert(conn, sql, batch)
        total += len(batch)

    conn.close()
    print(f"Imported {total} open schools")


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python import_schools.py <path-to-gias-csv>")
        sys.exit(1)
    run(sys.argv[1])
