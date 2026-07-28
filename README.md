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

`public/build/manifest.json` is required at runtime by the Blade `@vite`
directive. It is intentionally excluded from Git because it is a generated
artifact, so deployments that check out only the repository must run the build
step. If the production host does not provide Node.js, run `npm run build`
locally or in CI and upload the complete `public/build` directory to the
server, then run the Laravel cache commands.
