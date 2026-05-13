import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from import_epc import normalise_postcode, normalise_address, parse_floor_area, parse_rooms, transform_row

VALID_ROW = {
    'LMK_KEY': 'abc123def456',
    'ADDRESS1': '42 High Street',
    'ADDRESS2': '',
    'ADDRESS3': '',
    'POSTCODE': 'SW1A 1AA',
    'PROPERTY_TYPE': 'House',
    'BUILT_FORM': 'Semi-Detached',
    'INSPECTION_DATE': '2022-04-01',
    'TOTAL_FLOOR_AREA': '85.5',
    'NUMBER_HABITABLE_ROOMS': '4',
    'CURRENT_ENERGY_RATING': 'C',
    'CONSTRUCTION_AGE_BAND': '1967-1975',
}


class TestNormaliseAddress:
    def test_lowercases_and_strips(self):
        result = normalise_address('42 High Street', '', 'SW1A1AA')
        assert result == '42 high street'

    def test_removes_special_chars(self):
        result = normalise_address('Flat 2, Rose-Court', '', 'SW1A1AA')
        assert result == 'flat 2 rosecourt'

    def test_combines_address1_and_address2(self):
        result = normalise_address('Flat 1', 'Rose Court', 'SW1A1AA')
        assert result == 'flat 1 rose court'

    def test_handles_nan_string(self):
        result = normalise_address('42 High Street', 'nan', 'SW1A1AA')
        assert 'nan' not in result

    def test_collapses_whitespace(self):
        result = normalise_address('42   High   Street', '', 'SW1A1AA')
        assert '  ' not in result


class TestParseFloorArea:
    def test_valid_float(self):
        assert parse_floor_area('85.5') == 85.5

    def test_valid_integer_string(self):
        assert parse_floor_area('120') == 120.0

    def test_invalid_returns_none(self):
        assert parse_floor_area('unknown') is None

    def test_none_returns_none(self):
        assert parse_floor_area(None) is None


class TestParseRooms:
    def test_valid_integer(self):
        assert parse_rooms('4') == 4

    def test_float_string_rounds_down(self):
        assert parse_rooms('3.0') == 3

    def test_invalid_returns_none(self):
        assert parse_rooms('unknown') is None


class TestTransformRow:
    def test_valid_row_returns_tuple(self):
        result = transform_row(VALID_ROW)
        assert result is not None
        assert result[0] == 'abc123def456'
        assert result[4] == 'SW1A1AA'
        assert result[8] == 85.5
        assert result[9] == 4

    def test_postcode_normalised(self):
        result = transform_row(VALID_ROW)
        assert ' ' not in result[4]

    def test_address_normalized_present(self):
        result = transform_row(VALID_ROW)
        assert result[12] == '42 high street'

    def test_empty_lmk_key_returns_none(self):
        row = {**VALID_ROW, 'LMK_KEY': ''}
        assert transform_row(row) is None

    def test_empty_postcode_returns_none(self):
        row = {**VALID_ROW, 'POSTCODE': ''}
        assert transform_row(row) is None

    def test_energy_rating_truncated_to_one_char(self):
        result = transform_row(VALID_ROW)
        assert result[10] == 'C'

    def test_empty_address2_becomes_none(self):
        result = transform_row(VALID_ROW)
        assert result[2] is None
