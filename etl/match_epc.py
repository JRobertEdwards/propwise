import sys
import db

FUZZY_THRESHOLD = 0.6


def enable_pg_trgm(conn) -> None:
    with conn.cursor() as cur:
        cur.execute("CREATE EXTENSION IF NOT EXISTS pg_trgm")
    conn.commit()


def exact_match(conn) -> int:
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE property_sales ps
            SET epc_certificate_id = e.id,
                epc_match_confidence = 'exact'
            FROM epc_certificates e
            WHERE ps.postcode = e.postcode
              AND ps.epc_certificate_id IS NULL
              AND (
                  lower(e.address1) LIKE '%' || lower(ps.paon) || '%'
                  OR lower(e.address2) LIKE '%' || lower(ps.paon) || '%'
              )
        """)
        count = cur.rowcount
    conn.commit()
    return count


def fuzzy_match(conn, threshold: float = FUZZY_THRESHOLD) -> int:
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE property_sales ps
            SET epc_certificate_id = matched.id,
                epc_match_confidence = 'fuzzy'
            FROM (
                SELECT DISTINCT ON (ps.id)
                    ps.id AS sale_id,
                    e.id
                FROM property_sales ps
                JOIN epc_certificates e ON ps.postcode = e.postcode
                WHERE ps.epc_certificate_id IS NULL
                  AND similarity(
                      e.address_normalized,
                      lower(ps.paon || ' ' || coalesce(ps.street, ''))
                  ) > %(threshold)s
                ORDER BY ps.id, similarity(
                    e.address_normalized,
                    lower(ps.paon || ' ' || coalesce(ps.street, ''))
                ) DESC
            ) matched
            WHERE ps.id = matched.sale_id
        """, {'threshold': threshold})
        count = cur.rowcount
    conn.commit()
    return count


def mark_unmatched(conn) -> int:
    with conn.cursor() as cur:
        cur.execute("""
            UPDATE property_sales
            SET epc_match_confidence = 'none'
            WHERE epc_certificate_id IS NULL
              AND epc_match_confidence IS NULL
        """)
        count = cur.rowcount
    conn.commit()
    return count


def run(threshold: float = FUZZY_THRESHOLD) -> None:
    conn = db.connect()

    print("Enabling pg_trgm...")
    enable_pg_trgm(conn)

    print("Running exact match...")
    exact = exact_match(conn)
    print(f"  Exact matches: {exact:,}")

    print("Running fuzzy match...")
    fuzzy = fuzzy_match(conn, threshold)
    print(f"  Fuzzy matches: {fuzzy:,}")

    print("Marking unmatched...")
    unmatched = mark_unmatched(conn)
    print(f"  Unmatched: {unmatched:,}")

    conn.close()


if __name__ == '__main__':
    threshold = float(sys.argv[1]) if len(sys.argv) > 1 else FUZZY_THRESHOLD
    run(threshold)
