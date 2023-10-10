import {userData} from "./WebauthnTypes";

export default class Utilities {
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

    public static setNotification(message: string, isSuccess: boolean, target: HTMLElement): void {
        const notifyWrapper = document.querySelector('#login') as HTMLElement;
        const notification = document.createElement('p');
        notification.classList.add('message');
        if(isSuccess) {
            notification.classList.add('success');
        } else {
            notification.classList.add('error');
        }

        notification.innerText = message;

        notifyWrapper.insertBefore(notification, target);
    }
}



