import os
from pathlib import Path
from dotenv import load_dotenv

load_dotenv(Path(__file__).parent.parent / '.env')

# ETL connects from host (WSL) to the forwarded port, not via Docker hostname
DB_HOST = os.getenv('ETL_DB_HOST', 'localhost')
DB_PORT = os.getenv('DB_PORT', '5432')
DB_NAME = os.getenv('DB_DATABASE', 'propwise')
DB_USER = os.getenv('DB_USERNAME', 'sail')
DB_PASSWORD = os.getenv('DB_PASSWORD', 'password')

DSN = f"host={DB_HOST} port={DB_PORT} dbname={DB_NAME} user={DB_USER} password={DB_PASSWORD}"

CHUNK_SIZE = 10_000
