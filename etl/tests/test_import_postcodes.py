import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from import_postcodes import normalise_postcode, convert_bng_to_wgs84, transform_row


def test_normalise_postcode_removes_spaces():
    assert normalise_postcode('SW1A 1AA') == 'SW1A1AA'


def test_normalise_postcode_uppercases():
    assert normalise_postcode('sw1a1aa') == 'SW1A1AA'


def test_normalise_postcode_strips_whitespace():
    assert normalise_postcode('  SW1A 1AA  ') == 'SW1A1AA'


def test_convert_bng_to_wgs84_known_point():
    # Trafalgar Square: BNG 530034, 180381 ≈ 51.508°N, -0.128°E
    lat, lng = convert_bng_to_wgs84(530034, 180381)
    assert abs(lat - 51.508) < 0.01
    assert abs(lng - (-0.128)) < 0.01


def test_convert_bng_to_wgs84_returns_rounded():
    lat, lng = convert_bng_to_wgs84(530034, 180381)
    assert len(str(lat).split('.')[-1]) <= 7
    assert len(str(lng).split('.')[-1]) <= 7


def test_transform_row_valid():
    row = ('SW1A1AA', '10', '530034', '180381', 'extra')
    result = transform_row(row)
    assert result is not None
    assert result[0] == 'SW1A1AA'
    assert isinstance(result[1], float)  # lat
    assert isinstance(result[2], float)  # lng
    assert result[3].startswith('SRID=4326;POINT(')


def test_transform_row_zero_easting_returns_none():
    row = ('SW1A1AA', '10', '0', '0', 'extra')
    assert transform_row(row) is None


def test_transform_row_invalid_coords_returns_none():
    row = ('SW1A1AA', '10', 'not_a_number', '180381', 'extra')
    assert transform_row(row) is None


def test_transform_row_empty_postcode_returns_none():
    row = ('', '10', '530034', '180381', 'extra')
    assert transform_row(row) is None
