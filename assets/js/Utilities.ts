import { userData, NotificationStatus } from './WebauthnTypes';

export default class Utilities {
	public static async setUserData(
		userData: userData,
		restUrlEndpoint: string,
	): Promise<void> {
		try {
			const response: Response = await fetch( restUrlEndpoint + '/creds/user', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
				},
				body: JSON.stringify( userData ),
			} );
			if ( response.ok ) {
				await response.json();
			} else {
				console.error(
					`Server returned ${ response.status }: ${ response.statusText }`,
				);
			}
		} catch ( error ) {
			console.error( 'There was a problem with the fetch operation:', error );
		}
	}

	public static setNotification(
		message: string,
		type: keyof typeof NotificationStatus,
		target: HTMLElement,
		context?: string,
	): void {
		const existingNotifications = target.parentNode?.querySelectorAll(
			`.notice.notice-${ NotificationStatus[ type ] }`,
		);
		let exists = false;

		existingNotifications?.forEach( ( notif ) => {
			if ( notif.textContent === message ) {
				exists = true;
			}
		} );

		if ( exists ) {
			return;
		}

		const notificationWrapper = document.createElement( 'p' );
		notificationWrapper.classList.add(
			'notice',
			`notice-${ NotificationStatus[ type ].toLowerCase() }`,
		);
		if ( context === 'admin' ) {
			// TODO: Check if only adding is-dismissable is enough
			notificationWrapper.classList.add(
				'notice',
				`is-dismissable`,
				`notice-${ NotificationStatus[ type ].toLowerCase() }`,
			);
		}
		const notification = document.createElement( 'p' );
		notification.innerText = message;

		notificationWrapper.appendChild( notification );

		if ( target.parentNode ) {
			target.parentNode.insertBefore( notificationWrapper, target );
		}
	}
}
