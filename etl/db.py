import psycopg2
from psycopg2.extras import execute_values
from config import DSN


def connect():
    return psycopg2.connect(DSN)


def bulk_insert(conn, sql, rows, page_size=1000):
    with conn.cursor() as cur:
        execute_values(cur, sql, rows, page_size=page_size)
    conn.commit()
