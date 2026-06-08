# Meili / AssetMapper Notes

Date: 2026-05-03

## Current state

This app is a small Meilisearch example using `survos/meili-bundle` with Symfony AssetMapper/importmap.

The asset wiring issue around `survos/js-twig-bundle` was not primarily a Meili problem:

- `Survos\JsTwigBundle\SurvosJsTwigBundle` must be registered in `config/bundles.php` so AssetMapper sees `@survos/js-twig`.
- `Survos\TablerBundle\SurvosTablerBundle` must also be registered so Tabler controllers such as `accordion` resolve.
- The linked `js-twig-bundle` manifest now has explicit controller names:
  - `survos/js-twig/dexie`
  - `survos/js-twig/js_twig`
- Because the Dexie controller is currently enabled/eager, `dexie` must exist in `importmap.php`.

After compiling assets to test, purge `public/assets` again during development so Symfony does not serve stale compiled assets.

## Remaining Meili issue

The Meili frontend may still fail because of upstream `@meilisearch/instant-meilisearch` / `meilisearch` browser ESM compatibility.

Relevant upstream issues:

- https://github.com/meilisearch/meilisearch-js-plugins/issues/1472
- https://github.com/meilisearch/meilisearch-js-plugins/issues/1468
- https://github.com/meilisearch/meilisearch-js-plugins/pull/1474

Observed/known history:

- `@meilisearch/instant-meilisearch@0.30.0` imports `MeiliSearch` from `meilisearch`.
- Newer `meilisearch` JS packages renamed the export to `Meilisearch`.
- This breaks native browser ESM/importmap setups with errors like:
  - `meilisearch does not provide an export named MeiliSearch`
  - or related `instantMeiliSearch` export errors depending on the resolved package version/CDN bundle.
- `@meilisearch/instant-meilisearch@0.31.1` appears to address the `MeiliSearch` vs `Meilisearch` dependency break, but upstream is also moving toward `instantMeilisearch` casing.

## Next surgical check

Do not refactor broadly. Next time:

1. Resolve the actual downloaded file for `@meilisearch/instant-meilisearch` in `assets/vendor`.
2. Check its exports directly.
3. If needed, update `survos/meili-bundle`'s `insta_controller.js` to tolerate both export names:
   - `instantMeiliSearch`
   - `instantMeilisearch`
4. Keep the importmap pins explicit until the upstream package settles.

