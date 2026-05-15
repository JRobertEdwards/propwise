import sys
import pandas as pd
from pyproj import Transformer
from tqdm import tqdm
import db
from config import CHUNK_SIZE

_transformer = Transformer.from_crs("EPSG:27700", "EPSG:4326", always_xy=False)

COLUMNS = ['postcode', 'quality', 'easting', 'northing']


def normalise_postcode(postcode: str) -> str:
    return postcode.strip().upper().replace(' ', '')


def convert_bng_to_wgs84(easting: float, northing: float) -> tuple[float, float]:
    lat, lng = _transformer.transform(easting, northing)
    return round(lat, 7), round(lng, 7)


def transform_row(row) -> tuple | None:
    try:
        postcode = normalise_postcode(str(row[0]))
        easting = float(row[2])
        northing = float(row[3])
        if not postcode or easting == 0 or northing == 0:
            return None
        lat, lng = convert_bng_to_wgs84(easting, northing)
        return (postcode, lat, lng, f'SRID=4326;POINT({lng} {lat})')
    except (ValueError, IndexError):
        return None


def run(filepath: str) -> None:
    conn = db.connect()
    sql = """
        INSERT INTO postcodes (postcode, latitude, longitude, location)
        VALUES %s
        ON CONFLICT (postcode) DO NOTHING
    """

    for chunk in tqdm(pd.read_csv(filepath, header=None, chunksize=CHUNK_SIZE, dtype=str, keep_default_na=False), desc="Postcodes"):
        rows = [r for r in (transform_row(row) for row in chunk.itertuples(index=False)) if r]
        if rows:
            db.bulk_insert(conn, sql, rows)

    conn.close()


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print("Usage: python import_postcodes.py <path-to-codepoint-csv>")
        sys.exit(1)
    run(sys.argv[1])
