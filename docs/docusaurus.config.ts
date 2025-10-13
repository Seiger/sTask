import type {Config} from '@docusaurus/types';
import type {ThemeConfig} from '@docusaurus/preset-classic';

const config: Config = {
    title: 'sTask Docs',
    tagline: 'Asynchronous task management system for Evolution CMS. Handle background operations and task queues with reliability and performance.',
    url: 'https://seiger.github.io',
    baseUrl: '/sTask/',
    favicon: 'img/logo.svg',

    // GitHub Pages
    organizationName: 'Seiger',
    projectName: 'sTask',
    deploymentBranch: 'gh-pages',

    onBrokenLinks: 'throw',
    onBrokenMarkdownLinks: 'warn',

    i18n: {
        defaultLocale: 'en',
        locales: ['en', 'uk'],
        localeConfigs: {
            en: { label: 'English', htmlLang: 'en' },
            uk: { label: 'Українська', htmlLang: 'uk' },
        },
    },

    presets: [
        [
            'classic',
            {
                docs: {
                    path: 'pages',
                    routeBasePath: '/',
                    sidebarPath: require.resolve('./sidebars.ts'),
                    editLocalizedFiles: true,
                    includeCurrentVersion: true,
                },
                blog: false,
                theme: {
                    customCss: [
                        require.resolve('./src/css/theme.css'),
                        require.resolve('./src/css/tailwind.css'),
                    ]
                }
            }
        ]
    ],

    themeConfig: {
        navbar: {
            title: 'sTask Docs',
            logo: {
                alt: 'sTask',
                src: 'img/logo.svg',
                width: 24, height: 24
            },
            items: [
                {type: 'localeDropdown', position: 'right'}
            ]
        }
    } satisfies ThemeConfig
};

export default config;