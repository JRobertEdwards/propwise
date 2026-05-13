# Propwise — Project State

## Current Phase
**Phase 3 — Enhancements** (not started)

---

## What's Built

### Infrastructure
- Laravel 13 + Sail (Docker) — `./vendor/bin/sail up -d` to start
- Postgres 17 + PostGIS 3.5 + pg_trgm + Redis
- Laravel Telescope installed (`/telescope`) — dev only
- All running on `http://localhost`

### Database
- Migrations all passing — `postcodes`, `epc_certificates`, `property_sales`, `telescope_entries`
- PostGIS enabled via migration (`0001_01_01_000003`) — runs for both main and test DB
- pg_trgm enabled via migration (`2026_05_13_205539`)
- Geospatial indexes on `postcodes.location` and `property_sales.location`
- Composite index on `property_sales(postcode, property_type, sale_date)`

### Python ETL (`/etl`)
- `import_postcodes.py` — OS Code Point Open CSV → postcodes table (BNG → WGS84 via pyproj)
- `import_land_registry.py` — Land Registry Price Paid CSV → property_sales (excludes Cat B + deletions)
- `import_epc.py` — EPC register CSV → epc_certificates (normalises address for matching)
- `match_epc.py` — links EPC to sales: exact first, fuzzy (pg_trgm threshold 0.6) second, marks remainder 'none'
- Run: `cd etl && python3 -m pytest tests/ -v` — **56 passing**
- DB connection for ETL: localhost:5432 (host-forwarded). Set `ETL_DB_HOST=localhost` if needed.

### Laravel Models
- `Postcode` — string PK, no timestamps, `near()` scope (PostGIS radius)
- `EpcCertificate` — casts floor_area/rooms/date, `hasMany` sales
- `PropertySale` — casts price/new_build/sale_date, `withinRadius/ofType/soldBetween/withEpc` scopes, `price_per_sqm` accessor

### Repository Layer
Pattern: interface + concrete for all repositories. Bound in `AppServiceProvider`.
- `PostcodeRepositoryInterface` / `PostcodeRepository` — `findByPostcode(string): ?Postcode`
- `PropertySaleRepositoryInterface` / `PropertySaleRepository` — `search(SearchFilters): LengthAwarePaginator`
- `EpcCertificateRepository` — not yet needed (only accessed via eager load through PropertySale). Add in Phase 3 when aggregation queries come in.
- Rule: **all new controllers must inject a repository interface, never a model directly**

### Services
- `PostcodeLookupService` — normalises postcode string, delegates DB lookup to `PostcodeRepositoryInterface`

### API Layer
- `GET /api/search` — unauthenticated (auth deferred to Phase 4)
- Params: `postcode` (required), `radius` (0.5/1/2, default 1), `property_type[]` (D/S/T/F/O), `date_from`, `date_to`
- Returns paginated `PropertySaleResource` with distance_metres, price_per_sqm, epc_match_confidence
- `SearchFilters` DTO — carries search params, built from `SearchRequest` via `SearchFilters::fromRequest()`
- **46 passing tests**

### Frontend
- Alpine.js v3 + Tailwind v4 + Vite — built assets in `public/build/`
- `resources/views/layouts/app.blade.php` — base layout
- `resources/views/home.blade.php` — search page
- Alpine `search` component in `resources/js/app.js`
- Search form: postcode, radius (0.5/1/2 mi), property type checkboxes (D/S/T/F/O), date range
- Results: address, town, postcode, sold price, sale date, type badge, new-build badge, floor area, price/sqm, distance, EPC confidence badge (green=exact, amber=fuzzy)
- Loading skeleton, validation error display (inline 422 + general errors), prev/next pagination
- To rebuild assets: `./vendor/bin/sail npm run build`

---

## Next Steps (Phase 3)

- Price trend chart — sold price / price-per-sqm over time for the searched area
- Map view of results (Leaflet.js pinned to result locations)
- Summary statistics panel — median price, median £/sqm, number of sales in period
- `EpcCertificateRepository` — add when aggregate queries needed

---

## Key Decisions

| Decision | Choice | Rationale |
|---|---|---|
| DB | Postgres + PostGIS | Geospatial radius, large dataset |
| ETL | Python | pandas bulk processing, independent of web layer |
| Frontend | Blade + Alpine.js | MVP is request/response, no SPA needed |
| API | API-first (JSON) | Clean separation, .NET collaborator possibility |
| Auth | None yet | Deferred to Phase 4 (monetisation) |
| EPC match | exact → fuzzy → none | Show with confidence caveat in UI |
| Radius | Miles (converted to metres for PostGIS) | 1609.34m/mile |
| Repository pattern | Interface + concrete for all controllers | Decouples controllers from Eloquent, enables caching layer and .NET swap |
| Telescope | Dev only | Gated by local env in TelescopeServiceProvider |

---

## Open Questions
- Monetisation model (Phase 4)
- Potential .NET collaborator — revisit architecture if they join before Phase 3 is done

---

## Full Plan
See `/mnt/c/Users/Josh/var/apps/propwise-plan.md`
