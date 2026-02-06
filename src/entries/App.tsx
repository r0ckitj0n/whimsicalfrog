import React from 'react';
import { AppProvider } from '../context/AppContext.js';
import { NotificationProvider } from '../context/NotificationContext.js';
import { AuthProvider } from '../context/AuthContext.js';
import { ModalProvider } from '../context/ModalContext.js';
import { ErrorBoundary } from '../components/ui/ErrorBoundary.js';
import { AppShell } from '../components/AppShell.js';

export const App: React.FC = () => (
    <ErrorBoundary name="GlobalApp">
        <AppProvider>
            <NotificationProvider>
                <AuthProvider>
                    <ModalProvider>
                        <AppShell />
                    </ModalProvider>
                </AuthProvider>
            </NotificationProvider>
        </AppProvider>
    </ErrorBoundary>
);

export default App;
