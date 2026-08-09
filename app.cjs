'use strict';

import('./bootstrap/ssr/ssr.js').catch((error) => {
    console.error('Unable to start the Inertia SSR bundle.', error);
    process.exit(1);
});
