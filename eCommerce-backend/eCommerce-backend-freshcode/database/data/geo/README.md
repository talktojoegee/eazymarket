# Bundled geo reference data

Offline datasets seeded into `geo_states`, `geo_cities`, `geo_pincodes` by
`GeoImportService` (scoped to market countries). No runtime external calls.

## Files
- `geo_states.ndjson.gz`, `geo_cities.ndjson.gz` — from
  [dr5hn/countries-states-cities-database](https://github.com/dr5hn/countries-states-cities-database)
  (`json/countries+states+cities.json`). Rows: `{c: iso2, s: state_code, n: name}`.
- `geo_pincodes.ndjson.gz` — from GeoNames postal
  (`https://download.geonames.org/export/zip/allCountries.zip`). Rows:
  `{c: iso2, p: postcode, city, sc: state_code, sn: state_name}`.

## City field rule for pincodes (important)
GeoNames columns mean different things per country:
- **India** — `place` (col 3) is a neighbourhood/PO area, `admin3` (col 8) is the
  town/city (e.g. pincode 370110 → admin3 `Anjar`, not admin2 `Kachchh`). Use `admin3`.
- **Most others** (US/GB/AU/BR/…) — `admin3` is empty, `place` is the city. Use `place`.

Build rule: `city = admin3 || place` for countries in the `admin3` set (`IN`),
else `place || admin3`. Extend the set in the regenerate step if another country
shows the same neighbourhood-as-place pattern.

## Regenerate (refresh snapshot)
Download the sources above, flatten to the row shapes, dedupe by
`(country, key…)`, gzip as NDJSON (one JSON object per line so import streams
with flat memory), and replace the files here. Then `php artisan geo:sync --fresh`.
