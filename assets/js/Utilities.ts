import {UserLoginResponse} from "./AuthenticatorInterface";

export class Utilities {
    public static async setUserLogin(): Promise<UserLoginResponse | null> {
        const userLogin: HTMLInputElement | null = document.querySelector('#user_login');
        let userResponse: { isExistingUser?: boolean } = {};
        if (userLogin?.value) {
            try {
                const response: Response = await fetch(`/wp-json/wp-passkeys/creds/user/set?name=${userLogin.value}`, {
                    method: 'POST',
                });
                if (response.ok) {
                    userResponse = await response.json();
                } else {
                    console.error(`Server returned ${response.status}: ${response.statusText}`);
                }

            } catch (error) {
                console.error('There was a problem with the fetch operation:', error);
            }

            return userResponse;
        }

        return null;
    }
}



