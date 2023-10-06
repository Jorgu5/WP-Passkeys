import {userData} from "./WebauthnTypes";

export class Utilities {
    public static async setUserData(userData: userData): Promise<void> {
        try {
            const response: Response = await fetch(`/wp-json/wp-passkeys/creds/user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(userData),
            });
            if (response.ok) {
                await response.json();
            } else {
                console.error(`Server returned ${response.status}: ${response.statusText}`);
            }
        } catch (error) {
            console.error('There was a problem with the fetch operation:', error);
        }
    }
}



