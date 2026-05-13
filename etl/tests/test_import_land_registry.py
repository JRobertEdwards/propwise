import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from import_land_registry import parse_price, parse_date, parse_new_build, normalise_postcode, transform_row

VALID_ROW = {
    'transaction_id': '{A83B42C4-1234-5678-ABCD-000000000001}',
    'price': '265000',
    'sale_date': '2023-06-15 00:00',
    'postcode': 'SW1A 1AA',
    'property_type': 'T',
    'new_build': 'N',
    'estate_type': 'F',
    'paon': '42',
    'saon': '',
    'street': 'HIGH STREET',
    'locality': '',
    'town_city': 'LONDON',
    'district': 'WESTMINSTER',
    'county': 'GREATER LONDON',
    'ppd_category': 'A',
    'record_status': 'A',
}


class TestParsePrice:
    def test_valid_integer(self):
        assert parse_price('265000') == 265000

    def test_invalid_returns_none(self):
        assert parse_price('not_a_number') is None

    def test_none_returns_none(self):
        assert parse_price(None) is None


class TestParseDate:
    def test_with_time_component(self):
        assert parse_date('2023-06-15 00:00') == '2023-06-15'

    def test_without_time_component(self):
        assert parse_date('2023-06-15') == '2023-06-15'

    def test_invalid_returns_none(self):
        assert parse_date('not-a-date') is None

    def test_none_returns_none(self):
        assert parse_date(None) is None


class TestParseNewBuild:
    def test_y_is_true(self):
        assert parse_new_build('Y') is True

    def test_n_is_false(self):
        assert parse_new_build('N') is False

    def test_lowercase_y_is_true(self):
        assert parse_new_build('y') is True


class TestNormalisePostcode:
    def test_removes_space(self):
        assert normalise_postcode('SW1A 1AA') == 'SW1A1AA'

    def test_uppercases(self):
        assert normalise_postcode('sw1a1aa') == 'SW1A1AA'


class TestTransformRow:
    def test_valid_row_returns_tuple(self):
        result = transform_row(VALID_ROW)
        assert result is not None
        assert result[0] == 'A83B42C4-1234-5678-ABCD-000000000001'
        assert result[1] == 265000
        assert result[2] == '2023-06-15'
        assert result[3] == 'SW1A1AA'

    def test_strips_braces_from_transaction_id(self):
        result = transform_row(VALID_ROW)
        assert '{' not in result[0]
        assert '}' not in result[0]

    def test_empty_saon_becomes_none(self):
        result = transform_row(VALID_ROW)
        assert result[8] is None

    def test_deletion_record_returns_none(self):
        row = {**VALID_ROW, 'record_status': 'D'}
        assert transform_row(row) is None

    def test_category_b_returns_none(self):
        row = {**VALID_ROW, 'ppd_category': 'B'}
        assert transform_row(row) is None

    def test_missing_price_returns_none(self):
        row = {**VALID_ROW, 'price': ''}
        assert transform_row(row) is None

    def test_missing_postcode_returns_none(self):
        row = {**VALID_ROW, 'postcode': ''}
        assert transform_row(row) is None

    def test_missing_date_returns_none(self):
        row = {**VALID_ROW, 'sale_date': 'bad-date'}
        assert transform_row(row) is None
