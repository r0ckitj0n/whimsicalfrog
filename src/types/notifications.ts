export type NotificationType = 'success' | 'error' | 'warning' | 'info' | 'validation';

export interface INotificationAction {
    text: string;
    onClick: (event: React.MouseEvent | Event) => void;
    style?: 'primary' | 'secondary';
}

export interface INotificationOptions {
    title?: string | null;
    duration?: number;
    persistent?: boolean;
    actions?: INotificationAction[] | null;
    autoHide?: boolean;
    forceAdminRenderer?: boolean;
    [key: string]: unknown;
}

export interface INotification extends INotificationOptions {
    id: number;
    message: string;
    type: NotificationType;
    isVisible: boolean;
    isExiting: boolean;
}
