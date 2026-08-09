import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ReactDOMServer from 'react-dom/server';
import { route as ziggyRoute } from 'ziggy-js';
import { formatPageTitle } from './lib/seo';

const appName = process.env.VITE_APP_NAME || 'ایرگجت';
const ssrPort = Number(process.env.PORT || 13714);

createServer((page) => {
    const ziggy = {
        ...(page.props.ziggy as Record<string, unknown>),
        location: new URL((page.props.ziggy as { location: string }).location),
    };

    (globalThis as any).Ziggy = ziggy;
    (globalThis as any).route = (name?: string, params?: unknown, absolute?: boolean) =>
        (ziggyRoute as any)(name, params, absolute, ziggy);

    return createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => formatPageTitle(title, appName),
        resolve: (name) => resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
        setup: ({ App, props }) => <App {...props} />,
    });
}, ssrPort);
