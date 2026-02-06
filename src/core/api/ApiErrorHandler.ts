export class ApiErrorHandler {
    public static async handle(response: Response): Promise<never> {
        let serverMsg = '';
        let detailsStr = '';
        let rawBody = '';
        try {
            const ct = response.headers.get('content-type') || '';
            rawBody = await response.text();

            console.error('[ApiErrorHandler] Error Response Raw Body:', rawBody);

            if (ct.includes('application/json')) {
                const obj = rawBody ? JSON.parse(rawBody) : null;
                const msg = obj && (obj.error || obj.message || (obj.data && (obj.data.error || obj.data.message)));
                if (msg) serverMsg = String(msg);

                const det = obj && (obj.details || (obj.data && obj.data.details));
                if (det) {
                    try { detailsStr = ` details=${JSON.stringify(det).slice(0, 400)}`; } catch { /* Details serialization failed */ }
                }
            } else if (rawBody) {
                serverMsg = rawBody.slice(0, 200);
            }
        } catch { /* Response parsing failed - use bare HTTP error */ }

        const baseMsg = `HTTP ${response.status}: ${response.statusText}`;
        const finalMsg = serverMsg ? `${baseMsg} - ${serverMsg}${detailsStr}` : baseMsg;

        console.error('[ApiErrorHandler] Final Error Message:', finalMsg);

        if (typeof window !== 'undefined' && window.showNotification) {
            window.showNotification(serverMsg || baseMsg, 'error');
        }

        throw new Error(finalMsg);
    }
}
