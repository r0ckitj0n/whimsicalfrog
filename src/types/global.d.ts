import { ApiClient } from '../core/ApiClient.js';
import { ICartAPI } from '../commerce/cart/types.js';

/** Options for WF_Confirm dialog */
interface IConfirmOptions {
  title?: string;
  message: string;
  confirmText?: string;
  cancelText?: string;
  confirmStyle?: 'primary' | 'secondary' | 'danger' | 'warning' | 'confirm';
  iconKey?: string;
}

/** Options for WF_Alert dialog */
interface IAlertOptions {
  title?: string;
  message: string;
  buttonText?: string;
  iconKey?: string;
}

/** Item data for global popup */
interface IItemPopupData {
  sku: string;
  name: string;
  price: number | string;
  image?: string;
  image_url: string;
  stock?: number;
  [key: string]: unknown;
}

declare global {
  type GlobalNotificationType = 'success' | 'error' | 'warning' | 'info' | 'validation';

  interface GlobalNotificationAction {
    text: string;
    onClick: (event: unknown) => void;
    style?: 'primary' | 'secondary';
  }

  interface GlobalNotificationOptions {
    title?: string | null;
    duration?: number;
    persistent?: boolean;
    actions?: GlobalNotificationAction[] | null;
    autoHide?: boolean;
    forceAdminRenderer?: boolean;
    [key: string]: unknown;
  }

  interface WhimsicalFrog {
    Core: {
      version: string;
      initialized: boolean;
      debug: boolean;
      modules: Record<string, unknown>;
      config: Record<string, unknown>;
    };
    log: (...args: unknown[]) => void;
    warn: (...args: unknown[]) => void;
    error: (...args: unknown[]) => void;
    setDebug: (enabled: boolean) => void;
    registerModule: (name: string, moduleDef: unknown) => boolean;
    addModule: (name: string, moduleDef: unknown) => boolean;
    on: (event: string, cb: (...args: unknown[]) => void) => void;
    emit: (event: string, data: unknown) => void;
    off: (event: string, cb: (...args: unknown[]) => void) => void;
    utils: Record<string, unknown>;
    api: {
      request: <T>(url: string, options?: Record<string, unknown>) => Promise<T>;
      get: <T>(url: string, params?: Record<string, unknown>) => Promise<T>;
      post: <T>(url: string, body?: unknown) => Promise<T>;
      put: <T>(url: string, body?: unknown) => Promise<T>;
      delete: <T>(url: string, params?: Record<string, unknown>) => Promise<T>;
    };
    getState: () => unknown;
    getConfig: () => unknown;
    getModule: (name: string) => unknown;
    ready: (callback: (wf: WhimsicalFrog) => void) => void;
    notifySuccess?: (msg: string) => void;
    notifyError?: (msg: string) => void;
    GlobalModal?: {
      show: (sku: string, data?: unknown) => void;
    };
  }

  interface Window {
    wfNotifications?: {
      success: (msg: string) => void;
      error: (msg: string) => void;
      info: (msg: string) => void;
      warning?: (msg: string) => void;
      show?: (msg: string, type: string) => void;
    };
    showNotification: (message: string, type?: GlobalNotificationType, options?: GlobalNotificationOptions) => number;
    showSuccess: (message: string, options?: GlobalNotificationOptions) => number;
    showError: (message: string, options?: GlobalNotificationOptions) => number;
    showWarning: (message: string, options?: GlobalNotificationOptions) => number;
    showInfo: (message: string, options?: GlobalNotificationOptions) => number;
    showValidation: (message: string, options?: GlobalNotificationOptions) => number;
    showAdminSuccess: (message: string, options?: GlobalNotificationOptions) => number;
    showAdminError: (message: string, options?: GlobalNotificationOptions) => number;
    showAdminInfo: (message: string, options?: GlobalNotificationOptions) => number;
    showAdminWarning: (message: string, options?: GlobalNotificationOptions) => number;
    showAdminToast: (message: string, type?: GlobalNotificationType, options?: GlobalNotificationOptions) => number;
    hideNotification: (id: number) => void;
    clearNotifications: () => void;
    closeTopAdminModal: () => void;
    showToast: (typeOrMessage: string, messageOrType?: string | null, options?: GlobalNotificationOptions) => number;
    __WF_BACKEND_ORIGIN?: string;
    __WF_DEBUG?: boolean;
    __WF_DEV_MODE?: boolean;
    __WF_OPEN_CART_ON_ADD?: boolean;
    __WF_CART_MERGE_DUPES?: boolean;
    __WF_SHOW_UPSELLS?: boolean;
    __WF_CONFIRM_CLEAR_CART?: boolean;
    __WF_MINIMUM_CHECKOUT_TOTAL?: number;
    __WF_PENDING_CHECKOUT_AFTER_LOGIN?: boolean;
    __WF_SELECT_AUTOSORT_INSTALLED?: boolean;
    WFModalUtils?: {
      ensureOnBody: (el: HTMLElement | null) => HTMLElement | null;
      showModalById: (id: string) => boolean;
      hideModalById: (id: string) => boolean;
      forceVisibleStyles: (el: HTMLElement) => void;
    };
    showConfirmationModal?: (options: Record<string, unknown>) => Promise<unknown>;
    confirmAction?: (title: string, message: string, confirmText?: string) => Promise<boolean>;
    confirmDanger?: (title: string, message: string, confirmText?: string) => Promise<boolean>;
    confirmInfo?: (title: string, message: string, confirmText?: string) => Promise<boolean>;
    confirmSuccess?: (title: string, message: string, confirmText?: string) => Promise<boolean>;
    showAlertModal?: (options?: Record<string, unknown>) => Promise<unknown>;
    showPromptModal?: (options?: Record<string, unknown>) => Promise<unknown>;
    WF_Confirm?: (options: IConfirmOptions) => Promise<boolean>;
    WF_Alert?: (options: IAlertOptions) => Promise<void>;
    confirmWithDetails?: (title: string, message: string, details: string, options?: Record<string, unknown>) => Promise<unknown>;

    trackConversion?: (value: number, order_id: string | number | null) => void;
    trackCustomEvent?: (name: string, data: Record<string, unknown>) => void;
    optOutOfAnalytics?: () => void;
    optInToAnalytics?: () => void;
    analyticsTracker?: {
      trackConversion: (value: number, order_id: string | number | null) => void;
      trackCustomEvent: (name: string, data: Record<string, unknown>) => void;
      enableTracking: () => void;
      disableTracking: () => void;
    };
    showGlobalItemModal?: (sku: string, data?: unknown) => void;
    showDetailedModal?: (sku: string, data?: unknown) => void;
    showItemDetailsModal?: (sku: string, data?: unknown) => void;
    showItemDetails?: (sku: string, data?: unknown) => void;
    openEditModal?: (type?: string, id?: string | number) => void;
    openRoom?: (room_number: string | number) => void;
    openDeleteModal?: (type?: string, id?: string | number, name?: string) => void;
    performAction?: (action?: string) => void;
    runCommand?: (command?: string) => void;
    loadRoomConfig?: () => void;
    resetForm?: () => void;
    openLoginModal?: () => void;
    openCartModal?: () => void;
    openAccountSettings?: () => void;
    openPaymentModal?: () => void;
    WF_PaymentModal?: {
      open: () => void;
      close: () => void;
    };
    WF_ReceiptModal?: {
      open: (order_id: string | number) => void;
      close: () => void;
    };
    roomModalManager?: {
      init: () => void;
      show: (room_number: string | number) => void;
      hide: () => void;
      openRoom: (room_number: string | number) => void;
      getRoomData: (room_number: string | number) => Promise<{ content?: string; metadata?: Record<string, unknown> } | null>;
      preloadRoomContent: () => Promise<void>;
      preloadSingleRoom: (room_number: string | number) => Promise<{ content?: string; metadata?: Record<string, unknown> } | null>;
      invalidateRoom: (room_number: string | number) => void;
      clearCache: () => void;
    };
    showModal: (id: string) => void;
    hideModal: (id: string) => void;
    WFModals?: {
      lockScroll: () => void;
      unlockScrollIfNoneOpen: () => void;
    };
    hideGlobalPopupImmediate?: () => void;
    setupImageErrorHandling?: (img: HTMLImageElement, sku?: string) => void;
    WhimsicalFrog?: WhimsicalFrog;
    WF?: WhimsicalFrog;
    wf?: WhimsicalFrog;
    openOrderDetails?: (id: string | number) => void;
    checkItemSale?: (sku: string) => Promise<{ is_on_sale: boolean; discount_percentage?: number }>;
    calculateSalePrice?: (price: number, discount: number) => number;
    checkAndDisplaySalePrice?: (item: unknown, priceEl: HTMLElement, unitPriceEl?: HTMLElement | null) => Promise<void>;
    addSaleBadgeToCard?: (card: HTMLElement, discount: number | string) => Promise<void>;
    apiGet?: typeof ApiClient.get;
    apiPost?: typeof ApiClient.post;
    apiRequest?: typeof ApiClient.request;
    apiDelete?: typeof ApiClient.delete;
    apiPut?: typeof ApiClient.put;
    apiUpload?: typeof ApiClient.upload;
    Square?: {
      payments: (appId: string, locId: string) => unknown;
    };
    initializeRoomCoordinates?: (opts: unknown) => Promise<void>;
    attachDelegatedItemEvents?: () => void;
    setupPopupEventsAfterPositioning?: () => void;
    toggleGlobalTooltips?: (enabled: boolean) => void;
    refreshHeaderHeight?: () => void;
    __WF_SHARED_ADMIN_CSS?: {
      count: number;
      sources: string[];
      warned: boolean;
    };
    // ... (rest of the properties)
    showItemDetails?: (sku: string) => void;
    confirmDialog?: (message: string, title?: string) => Promise<boolean>;
    showGlobalPopup?: (anchorEl: HTMLElement, item: IItemPopupData) => void;
    hideGlobalPopup?: () => void;
    scheduleHideGlobalPopup?: (delay?: number) => void;
    cancelHideGlobalPopup?: () => void;
    searchModal?: unknown;
    SearchModal?: unknown;
    centralFunctions?: unknown;
    __WF_CART_OVERRIDE?: unknown;
    room_number?: string | number;
    originalImageWidth?: number;
    originalImageHeight?: number;
    trackPageView?: (page: string) => void;
    trackAddToCart?: (item: unknown) => void;
    trackRemoveFromCart?: (sku: string) => void;
    trackSearch?: (term: string, count: number) => void;
    trackRoomView?: (room_number: string | number) => void;
    WF_Cart?: ICartAPI;
    cart?: ICartAPI;
    WFToast?: {
      success: (message: string) => void;
      error: (message: string) => void;
      info: (message: string) => void;
      warning: (message: string) => void;
      toastSuccess: (message: string | null | undefined) => void;
      toastError: (message: string | null | undefined) => void;
      toastFromData: (data: { success: boolean; message?: string; error?: string } | null, fallbackSuccess?: string) => void;
    };
    EventBus?: unknown;
    eventBus?: unknown;
    DOMUtils?: unknown;
    debounce?: unknown;
    formatCurrency?: unknown;
    escapeHtml?: unknown;
    notifySuccess?: (msg: string) => void;
    notifyError?: (msg: string) => void;
    __wfNoRouter?: boolean;
    __wfDiagMinimalApp?: boolean;
    __wfDiagNoPopup?: boolean;
    __wfAppMinimal?: boolean;
    __wfAppConfig?: unknown;
    __wfAllowEvents?: boolean;
    __wfDiagNoDelegated?: boolean;
    __wfDiagNoAdminStd?: boolean;
    __wfDiagNoAutosize?: boolean;
    __wfAllowItemModal?: boolean;
    __wfAllowPaymentModal?: boolean;
    __wfForceReceiptModal?: boolean;
  }

  interface HTMLElement {
    _wfStrictImageGuard?: (event: ErrorEvent) => void;
  }
}
