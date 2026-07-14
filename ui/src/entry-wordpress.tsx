import '@middag-io/react/style.css';
import './theme.css';

import { createInertiaApp } from '@inertiajs/react';
import { I18nProvider, ProgressProvider } from '@middag-io/react';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { resolvePage } from './app/page-resolver';
import { registerUi } from './app/register';

declare global {
  interface Window {
    wp?: { i18n?: { __: (text: string, domain: string) => string } };
  }
}

// Register the shell + blocks this plugin uses (selective — never registerDefaults).
registerUi();

void createInertiaApp({
  // MUST equal the PHP InertiaAdapter::mountId() → '{componentName}-app'.
  id: 'middag-boilerplate-app',
  resolve: (name) => resolvePage(name),
  progress: { color: '#2563eb' },
  setup({ el, App, props }) {
    createRoot(el).render(
      <StrictMode>
        <ProgressProvider>
          <I18nProvider
            asyncResolver={async (key) => window.wp?.i18n?.__(key, 'middag-boilerplate') ?? key}
          >
            <App {...props} />
          </I18nProvider>
        </ProgressProvider>
      </StrictMode>,
    );
  },
});
