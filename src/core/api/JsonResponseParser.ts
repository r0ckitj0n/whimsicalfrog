export class JsonResponseParser {
    public static async parse<T>(response: Response): Promise<T> {
        const text = await response.text();
        const trimmed = (text || '').trim();

        if (!trimmed) return {} as T;

        let data: unknown;
        try {
            let cleanJson = trimmed;
            const firstBrace = trimmed.indexOf('{');
            const firstBracket = trimmed.indexOf('[');

            let startIdx = -1;
            let endIdx = -1;

            if (firstBrace !== -1 && (firstBracket === -1 || firstBrace < firstBracket)) {
                startIdx = firstBrace;
                endIdx = trimmed.lastIndexOf('}');
            } else if (firstBracket !== -1) {
                startIdx = firstBracket;
                endIdx = trimmed.lastIndexOf(']');
            }

            if (startIdx !== -1 && endIdx !== -1 && endIdx > startIdx) {
                cleanJson = trimmed.substring(startIdx, endIdx + 1);
            }

            if (cleanJson.startsWith('[') || cleanJson.startsWith('{')) {
                try {
                    data = JSON.parse(cleanJson);
                } catch (e) {
                    let balance = 0;
                    let foundEnd = -1;
                    const opener = cleanJson[0];
                    const closer = opener === '{' ? '}' : ']';

                    for (let i = 0; i < cleanJson.length; i++) {
                        if (cleanJson[i] === opener) balance++;
                        if (cleanJson[i] === closer) balance--;
                        if (balance === 0 && i > 0) {
                            foundEnd = i;
                            break;
                        }
                    }

                    if (foundEnd !== -1) {
                        cleanJson = cleanJson.substring(0, foundEnd + 1);
                        data = JSON.parse(cleanJson);
                    } else {
                        throw e;
                    }
                }
            } else {
                data = JSON.parse(cleanJson);
            }
        } catch (e) {
            console.error('[JsonResponseParser] JSON Parse Error:', e, 'Raw text:', trimmed);
            throw new Error('Invalid JSON response from server.');
        }

        // Type guard: ensure data is an object for property access
        const obj = (data && typeof data === 'object' && !Array.isArray(data))
            ? data as Record<string, unknown>
            : null;

        const isSuccess = obj && (obj.success === true || obj.success === 'true' || obj.success === 1 || obj.success === '1' || obj.order_id);
        const isFailure = obj && (obj.success === false || obj.success === 'false' || obj.success === 0 || obj.success === '0') && !obj.order_id;

        if (isFailure) {
            const errorMsg = (obj.error || obj.message || 'API request failed') as string;
            console.error('[JsonResponseParser] API reported failure:', errorMsg, data);
            throw new Error(errorMsg);
        }

        if (isSuccess && obj.data && typeof obj.data === 'object' && !Array.isArray(obj.data)) {
            return {
                success: true,
                message: obj.message || undefined,
                order_id: obj.order_id || undefined,
                ...(obj.data as Record<string, unknown>)
            } as T;
        }

        return data as T;
    }
}
