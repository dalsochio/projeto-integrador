import {defineConfig, loadEnv} from 'vite'
import {resolve} from 'path'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({mode}) => {
    const env = loadEnv(mode, process.cwd(), '');
    const isProduction = mode === 'production';

    return {
        base: isProduction ? '/public/assets/' : '/',
        plugins:
            [
                tailwindcss(),
            ],
        build:
            {
                outDir: './public/assets',
                emptyOutDir:
                    false,
                copyPublicDir:
                    false,
                rollupOptions:
                    {
                        input: {
                            'js/app':
                                resolve(__dirname, 'app/views/assets/js/app.js'),
                            'css/app':
                                resolve(__dirname, 'app/views/assets/css/app.css')
                        },
                        output: {
                            entryFileNames: '[name].js',
                            chunkFileNames:
                                'chunks/[name]-[hash].js',
                            assetFileNames:
                                (assetInfo) => {
                                    const extType = assetInfo.name.split('.').pop();
                                    const fontExtensions = ['woff', 'woff2', 'eot', 'ttf', 'otf'];

                                    if (fontExtensions.includes(extType)) {
                                        return `fonts/[name].[ext]`;
                                    }
                                    return `[name].[ext]`;
                                },
                        }
                    },
                sourcemap: true,
                minify:
                    'terser',
            },
        server: {
            watch: {
                ignored: [
                    '**/{node_modules,storage,scripts}/**'
                ]
            },
            allowedHosts: ['localhost:8080'],
            https:
                false,
            host:
                true,
            hmr:
                {
                    host: 'localhost',
                },
            cors: {
                origin: (origin, callback) => {
                    callback(null, true);
                },
                methods:
                    ['GET', 'HEAD', 'PUT', 'POST', 'DELETE', 'PATCH', 'OPTIONS'],
                credentials:
                    true
            },
        },
    }
})
