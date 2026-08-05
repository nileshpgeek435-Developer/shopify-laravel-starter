import js from '@eslint/js';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import react from 'eslint-plugin-react';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    {
        ignores: ['vendor', 'node_modules', 'public/build'],
    },

    js.configs.recommended,

    ...tseslint.configs.recommended,

    {
        files: ['**/*.{js,jsx,ts,tsx}'],

        languageOptions: {
            globals: globals.browser,
        },

        plugins: {
            react,
            'react-hooks': reactHooks,
            'react-refresh': reactRefresh,
        },

        rules: {
            ...reactHooks.configs.recommended.rules,

            'react/react-in-jsx-scope': 'off',

            'react-refresh/only-export-components': [
                'warn',
                {
                    allowConstantExport: true,
                },
            ],
        },

        settings: {
            react: {
                version: 'detect',
            },
        },
    }
);
