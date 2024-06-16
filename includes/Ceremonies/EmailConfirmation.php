<?php

namespace WpPasskeys\Ceremonies;

class EmailConfirmation
{
    public const TRANSIENT_PREFIX = 'email_verify_';
    public const TOKEN_EXPIRATION = 24 * 60 * 60; // 24 hours

    /**
     * Send the confirmation email.
     *
     * @param string $email User email.
     */
    public function sendConfirmationEmail(string $email): void
    {
        $token = $this->generateToken($email);
        $link  = $this->generateConfirmationLink($email, $token);

        $subject = 'Confirm Your Email Address';
        $message = "Please click on the following link to confirm your email address 
        and complete your registration: {$link}";

        wp_mail($email, $subject, $message);
    }

    /**
     * Generate a unique verification token for the email and store it temporarily.
     *
     * @param string $email User email.
     *
     * @return string The generated token.
     */
    protected function generateToken(string $email): string
    {
        $token = wp_generate_password(20, false);

        // Store the token temporarily with the email as part of the transient key
        set_transient(self::TRANSIENT_PREFIX . md5($email), $token, self::TOKEN_EXPIRATION);

        return $token;
    }

    /**
     * Generate the verification link.
     *
     * @param string $email User email.
     * @param string $token Verification token.
     *
     * @return string Verification link.
     */
    protected function generateConfirmationLink(string $email, string $token): string
    {
        return add_query_arg([
            'email'        => urlencode($email),
            'pkEmailToken' => $token,
        ], wp_registration_url());
    }

    /**
     * Handle the email verification. Verify the token.
     *
     * @param string $email User email.
     * @param string $token Verification token.
     *
     * @return bool True if verified, false otherwise.
     */
    public function confirmUserEmail(string $email, string $token): bool
    {
        $storedToken = get_transient(self::TRANSIENT_PREFIX . md5($email));

        if ($token === $storedToken) {
            delete_transient(self::TRANSIENT_PREFIX . md5($email));

            return true;
        }

        return false;
    }
}
