import { cart as sharedCart } from './cart-system.js';
import { ICartAPI } from './cart/types.js';

let overrideCart: ICartAPI | null = null;

function resolveCart(): ICartAPI {
  if (typeof window !== 'undefined') {
    const win = window as unknown as { __WF_CART_OVERRIDE?: ICartAPI };
    if (win.__WF_CART_OVERRIDE) {
      return win.__WF_CART_OVERRIDE;
    }
  }
  return overrideCart || sharedCart;
}

const proxyCart = new Proxy({} as ICartAPI, {
  get(_target, prop) {
    const cart = resolveCart();
    const value = cart[prop as keyof ICartAPI];
    if (typeof value === 'function') {
      return (value as Function).bind(cart);
    }
    return value;
  },
});

export const cart = proxyCart;

export function getActiveCart(): ICartAPI {
  return proxyCart;
}

export function setCartOverride(cartInstance: ICartAPI | null) {
  overrideCart = cartInstance || null;
  if (typeof window !== 'undefined') {
    const win = window as unknown as { __WF_CART_OVERRIDE?: ICartAPI | null };
    if (cartInstance) {
      win.__WF_CART_OVERRIDE = cartInstance;
    } else {
      delete win.__WF_CART_OVERRIDE;
    }
  }
}

export function clearCartOverride() {
  setCartOverride(null);
}
