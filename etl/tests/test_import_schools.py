import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..'))

from import_schools import convert_bng_to_wgs84, parse_row


def _row(**overrides) -> dict:
    base = {
        'URN': '100000',
        'EstablishmentName': 'Test School',
        'TypeOfEstablishment (name)': 'Community school',
        'PhaseOfEducation (name)': 'Primary',
        'EstablishmentStatus (name)': 'Open',
        'Postcode': 'EC3A 5DE',
        'Easting': '533498',
        'Northing': '181201',
    }
    base.update(overrides)
    return base


def test_convert_bng_to_wgs84_known_point():
    # Trafalgar Square: BNG 530034, 180381 ≈ 51.508°N, -0.128°E
    lat, lng = convert_bng_to_wgs84(530034, 180381)
    assert abs(lat - 51.508) < 0.01
    assert abs(lng - (-0.128)) < 0.01


def test_convert_bng_to_wgs84_returns_rounded():
    lat, lng = convert_bng_to_wgs84(530034, 180381)
    assert len(str(lat).split('.')[-1]) <= 7
    assert len(str(lng).split('.')[-1]) <= 7


def test_parse_row_valid():
    result = parse_row(_row())
    assert result is not None
    urn, name, school_type, phase, postcode, lat, lng, location = result
    assert urn == 100000
    assert name == 'Test School'
    assert school_type == 'Community school'
    assert phase == 'Primary'
    assert postcode == 'EC3A5DE'
    assert isinstance(lat, float)
    assert isinstance(lng, float)
    assert location.startswith('SRID=4326;POINT(')


def test_parse_row_filters_non_open():
    assert parse_row(_row(**{'EstablishmentStatus (name)': 'Closed'})) is None
    assert parse_row(_row(**{'EstablishmentStatus (name)': 'Proposed to open'})) is None


def test_parse_row_zero_coordinates_returns_none():
    assert parse_row(_row(Easting='0', Northing='0')) is None


def test_parse_row_missing_coordinates_returns_none():
    assert parse_row(_row(Easting='', Northing='')) is None


def test_parse_row_invalid_coordinates_returns_none():
    assert parse_row(_row(Easting='not_a_number', Northing='181201')) is None


def test_parse_row_empty_name_returns_none():
    assert parse_row(_row(**{'EstablishmentName': ''})) is None


def test_parse_row_invalid_urn_returns_none():
    assert parse_row(_row(URN='not_a_number')) is None


def test_parse_row_normalises_postcode():
    result = parse_row(_row(Postcode='EC3A 5DE'))
    assert result[4] == 'EC3A5DE'


def test_parse_row_empty_type_becomes_none():
    result = parse_row(_row(**{'TypeOfEstablishment (name)': ''}))
    assert result[2] is None


def test_parse_row_empty_phase_becomes_none():
    result = parse_row(_row(**{'PhaseOfEducation (name)': ''}))
    assert result[3] is None
