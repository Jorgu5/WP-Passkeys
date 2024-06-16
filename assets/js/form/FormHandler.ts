import RegistrationHandler from '../registration/Registration';
import Authentication from '../authentication/Authentication';
import Utilities from '../Utilities';
import { userData } from '../WebauthnTypes';

export class FormHandler {
	private loginForm: HTMLFormElement | null =
		document.querySelector( '#loginform' );
	private registerForm: HTMLFormElement | null =
		document.querySelector( '#registerform' );
	private passkeysButton: HTMLButtonElement | null =
		document.querySelector( '.passkeys-button' );
	private loginButton: HTMLInputElement | null | undefined =
		this.loginForm?.querySelector( '#wp-submit' );
	private registerButton: HTMLInputElement | null | undefined =
		this.registerForm?.querySelector( '#wp-submit' );

	async handleLogin(): Promise<void> {
		try {
			if ( this.loginForm ) {
				const authHandler = new Authentication();
				const authInit = await authHandler.init( false );
				await authHandler.start( authInit );
			}
		} catch ( error: any ) {
			console.error( 'An error occurred during form submission:', error );
		}
	}

	async handleRegistration(): Promise<void> {
		const userDataInputs = this.registerForm?.querySelectorAll(
			'input',
		) as NodeListOf<HTMLInputElement>;
		if ( userDataInputs && this.validatedRegisterFields( userDataInputs ) ) {
			try {
				Utilities.setUserData(
					this.getAllRegisterFormInputValues( userDataInputs ),
					pkUser.restEndpoints.main,
				).then( async () => {
					const regHandler = new RegistrationHandler();
					await regHandler.start();
				} );
			} catch ( error: any ) {
				console.error( 'An error occurred during form submission:', error );
			}
		} else {
			const regHandler = new RegistrationHandler();
			await regHandler.start();
		}
	}

	initForm(): void {
		const submitButton: HTMLInputElement | null =
      document.querySelector( '.submit' );
		if (
			this.isAnyFormPresentAndButtonsExist( this.passkeysButton, submitButton )
		) {
			const wrapper = this.createWrapper();
			this.wrapButtons( wrapper, submitButton, this.passkeysButton );
			this.appendToForms( wrapper );
			this.fetchAndFillUserCredentials()
				.then( () => {
					console.log( 'User credentials fetched and filled' );
				} )
				.catch( ( error ) => {
					console.error( 'Error fetching and filling user credentials:', error );
				} );
		}

		// Pass the button element directly
		this.addEventToForm(
			this.loginForm,
			this.passkeysButton,
			this.handleLogin.bind( this ),
		);
		this.addEventToForm(
			this.registerForm,
			this.passkeysButton,
			this.handleRegistration.bind( this ),
		);
	}

	async fetchAndFillUserCredentials(): Promise<void> {
		try {
			const response = await fetch( '/wp-json/wp-passkeys/creds/user', {
				method: 'GET',
				headers: {
					'Content-Type': 'application/json',
				},
			} );

			if ( ! response.ok ) {
				return;
			}

			const { data } = await response.json();

			if ( data && data.user_credentials ) {
				this.fillFormWithUserCredentials( data.user_credentials );
			}
		} catch ( error ) {
			console.error( 'Error fetching user credentials:', error );
		}
	}

	private addEventToForm(
		form: HTMLFormElement | null,
		button: HTMLElement | null,
		handler: EventListener,
	): void {
		if ( form && button ) {
			this.revealPasswordClickEvent();
			button.addEventListener( 'click', handler );
		}
	}

	private isAnyFormPresentAndButtonsExist(
		passkeysButton: HTMLButtonElement | null,
		submitButton: HTMLInputElement | null,
	): boolean {
		return Boolean(
			( passkeysButton && this.loginForm ) ||
        ( passkeysButton && this.registerForm && submitButton ),
		);
	}

	private createWrapper(): HTMLDivElement {
		const wrapper = document.createElement( 'div' );
		wrapper.classList.add( 'passkeys-login__wrapper' );
		return wrapper;
	}

	private wrapButtons(
		wrapper: HTMLDivElement,
		submitButton: HTMLInputElement | null,
		passkeysButton: HTMLButtonElement | null,
	): void {
		if ( submitButton ) {
			wrapper.appendChild( submitButton );
			wrapper.appendChild( passkeysButton as HTMLInputElement );
		}
	}

	private appendToForms( wrapper: HTMLDivElement ): void {
		if ( this.loginForm ) {
			this.loginForm.appendChild( wrapper );
		}
		if ( this.registerForm ) {
			this.registerForm.appendChild( wrapper );
		}
	}

	private getAllRegisterFormInputValues(
		userDataInputs: NodeListOf<HTMLInputElement>,
	): userData {
		const userData: userData = {};

		userDataInputs.forEach( ( input ) => {
			const userKey = input.name as keyof userData;
			if ( userKey ) {
				userData[ userKey ] = input.value;
			}
		} );

		return userData;
	}

	private validatedRegisterFields(
		userDataInputs: NodeListOf<HTMLInputElement>,
	): boolean {
		const requiredFields = Array.from( userDataInputs ).filter(
			( field ) => field.required,
		);
		const emptyFields = requiredFields.filter( ( field ) => ! field.value );
		emptyFields.forEach( ( field ) => {
			field.classList.add( 'error' );
		} );

		if ( emptyFields.length ) {
			Utilities.setNotification(
				'Please fill in all required fields.',
				'Error',
        this.registerForm as HTMLElement,
			);
			return false;
		}

		return true;
	}

	private revealPasswordClickEvent(): void {
		const passwordWrapper = this.loginForm?.querySelector(
			'.user-pass-wrap',
		) as HTMLElement;
		const passwordInput = this.loginForm?.querySelector(
			'input[type="password"]',
		) as HTMLInputElement;
		if ( passwordWrapper && this.loginButton ) {
			this.loginButton.addEventListener( 'click', ( e: Event ) => {
				const isHidden = passwordWrapper.hidden;
				if ( isHidden ) {
					e.preventDefault();
					passwordWrapper.hidden = false;
					passwordInput.disabled = false;
				}
			} );
		}
	}

	private fillFormWithUserCredentials( credentials: {
    [key: string]: string;
  } ): void {
		if ( this.registerForm ) {
			Object.keys( credentials ).forEach( ( key ) => {
				console.log( key );
				const input = this.registerForm?.querySelector(
					`input[name="${ key }"]`,
				) as HTMLInputElement;
				if ( input ) {
					input.value = credentials[ key ];
				}
			} );
		}
	}
}

document.addEventListener( 'DOMContentLoaded', () => {
	const formHandler = new FormHandler();
	formHandler.initForm();
} );
