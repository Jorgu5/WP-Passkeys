/**
 * Central type definitions for the wp-passkeys plugin
 * This file organizes all shared types in one location for better maintainability
 */

import type {
  AuthenticationResponseJSON,
  PublicKeyCredentialRequestOptionsJSON,
} from '@simplewebauthn/typescript-types';

/**
 * Notification related types
 */
export type NotifyFunctionType = (message: string, isSuccess: boolean) => void;

export enum NotificationStatus {
  Error = 'error',
  Success = 'success',
  Info = 'info',
}

/**
 * API related types
 */
export type ApiResponse = {
  code: string | number;
  message: string;
  data?: {
    redirectUrl?: string;
    pk_credential_id?: string;
    cardHtml?: string;
  };
};

/**
 * Context and user data types
 */
export type ContextType = {
  nonce?: string;
  restEndpoints: {
    [key: string]: string;
  };
};

export type UserData = {
  user_login?: string;
  user_email?: string;
  display_name?: string;
};

/**
 * Authenticator interface
 */
export interface AuthenticatorInterface {
  generateOptions(): Promise<PublicKeyCredentialRequestOptionsJSON>;
  init(isAutofill: boolean): Promise<AuthenticationResponseJSON>;
  start(authResp: AuthenticationResponseJSON, id?: string): Promise<void>;
}

/**
 * Re-export types from WebauthnTypes for backward compatibility
 */
export * from './WebauthnTypes'; 