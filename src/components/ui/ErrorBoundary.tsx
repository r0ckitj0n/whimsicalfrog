import React, { Component, ErrorInfo, ReactNode } from 'react';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
    name?: string;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

export class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null
    };

    public static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error(`[ErrorBoundary:${this.props.name || 'Global'}] Uncaught error:`, error, errorInfo);
    }

    public render() {
        if (this.state.hasError) {
            return this.props.fallback || (
                <div className="p-8 text-center bg-red-50 border border-red-200 rounded-xl m-4">
                    <h2 className="text-xl font-bold text-red-700 mb-2">Something went wrong</h2>
                    <p className="text-red-600 mb-4">{this.state.error?.message || 'An unexpected error occurred in the React application.'}</p>
                    <button 
                        onClick={() => window.location.reload()}
                        className="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
                    >
                        Reload Page
                    </button>
                    <div className="mt-4 text-left text-xs text-gray-500 overflow-auto max-h-40 p-2 bg-gray-100 rounded">
                        <pre>{this.state.error?.stack}</pre>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export default ErrorBoundary;
