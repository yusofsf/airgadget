# AirGadget

Laravel + React storefront for mobile accessories.

## Production deployment

The application uses Laravel Vite integration. Before each deployment, generate
the frontend assets and deploy the resulting `public/build` directory alongside
the PHP application:

```sh
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
```

The production build also generates `bootstrap/ssr/ssr.js`. Keep the Inertia
SSR process running under the host's process manager so public pages contain
their content, title, meta tags, and structured data in the initial HTML:

```sh
php artisan inertia:start-ssr
```

Restart that process after every frontend deployment. Its internal endpoint is
configured with `INERTIA_SSR_URL` and should not be exposed publicly. Set
`INERTIA_SSR_ENABLED=false` only on hosts where SSR is intentionally disabled.
The SSR entry also honors cPanel/Passenger's automatically assigned `PORT`.
For a cPanel Node.js application, use the project root as the application root
and select the repository's `app.cjs` Passenger-compatible wrapper as the
startup file.

Product images uploaded from the admin panel are stored under
`storage/app/product-images` and served through the public
`/product-images/{filename}` route. The production web-server user must have
write access to `storage` (the normal Laravel production requirement). This
route means product images do not depend on a `public/storage` symbolic link or
write access to the public web root.

`public/build/manifest.json` is required at runtime by the Blade `@vite`
directive. It is intentionally excluded from Git because it is a generated
artifact, so deployments that check out only the repository must run the build
step. If the production host does not provide Node.js, run `npm run build`
locally or in CI and upload both `public/build` and `bootstrap/ssr` to the
server, then run the Laravel cache commands. SSR still requires a Node.js
runtime on the production host.
