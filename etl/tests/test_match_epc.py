import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

import pytest
from unittest.mock import MagicMock, call
from match_epc import enable_pg_trgm, exact_match, fuzzy_match, mark_unmatched


def make_conn(rowcount=10):
    cur = MagicMock()
    cur.__enter__ = lambda s: cur
    cur.__exit__ = MagicMock(return_value=False)
    cur.rowcount = rowcount
    conn = MagicMock()
    conn.cursor.return_value = cur
    return conn, cur


class TestEnablePgTrgm:
    def test_creates_extension(self):
        conn, cur = make_conn()
        enable_pg_trgm(conn)
        cur.execute.assert_called_once_with("CREATE EXTENSION IF NOT EXISTS pg_trgm")
        conn.commit.assert_called_once()


class TestExactMatch:
    def test_executes_update_and_commits(self):
        conn, cur = make_conn(rowcount=42)
        result = exact_match(conn)
        assert cur.execute.called
        conn.commit.assert_called_once()
        assert result == 42

    def test_sql_targets_unmatched_only(self):
        conn, cur = make_conn()
        exact_match(conn)
        sql = cur.execute.call_args[0][0]
        assert 'epc_certificate_id IS NULL' in sql
        assert "epc_match_confidence = 'exact'" in sql


class TestFuzzyMatch:
    def test_executes_update_and_commits(self):
        conn, cur = make_conn(rowcount=15)
        result = fuzzy_match(conn, threshold=0.6)
        assert cur.execute.called
        conn.commit.assert_called_once()
        assert result == 15

    def test_passes_threshold_as_parameter(self):
        conn, cur = make_conn()
        fuzzy_match(conn, threshold=0.75)
        call_args = cur.execute.call_args
        assert call_args[0][1]['threshold'] == 0.75

    def test_sql_targets_unmatched_only(self):
        conn, cur = make_conn()
        fuzzy_match(conn)
        sql = cur.execute.call_args[0][0]
        assert 'epc_certificate_id IS NULL' in sql
        assert "epc_match_confidence = 'fuzzy'" in sql


class TestMarkUnmatched:
    def test_executes_update_and_commits(self):
        conn, cur = make_conn(rowcount=100)
        result = mark_unmatched(conn)
        assert cur.execute.called
        conn.commit.assert_called_once()
        assert result == 100

    def test_sql_only_touches_unconfidenced_rows(self):
        conn, cur = make_conn()
        mark_unmatched(conn)
        sql = cur.execute.call_args[0][0]
        assert 'epc_match_confidence IS NULL' in sql
        assert "epc_match_confidence = 'none'" in sql
