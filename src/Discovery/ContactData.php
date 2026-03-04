<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Forrst\Discovery;

use Cline\Forrst\Exceptions\FieldExceedsMaxLengthException;
use Cline\Forrst\Exceptions\HtmlNotAllowedException;
use Cline\Forrst\Exceptions\InvalidEmailException;
use Cline\Forrst\Exceptions\InvalidProtocolException;
use Cline\Forrst\Exceptions\InvalidUrlException;
use Cline\Forrst\Exceptions\MissingRequiredFieldException;
use Cline\Forrst\Exceptions\WhitespaceOnlyException;
use Spatie\LaravelData\Data;

use const FILTER_VALIDATE_EMAIL;
use const FILTER_VALIDATE_URL;

use function filter_var;
use function in_array;
use function mb_rtrim;
use function mb_strlen;
use function mb_strtolower;
use function mb_trim;
use function parse_url;
use function strip_tags;

/**
 * Contact information for the API service or team.
 *
 * Provides contact details for the individuals or teams responsible for the API,
 * enabling API consumers to reach out for support, questions, or collaboration.
 * Typically included in discovery documents to facilitate communication.
 *
 * @author Brian Faust <brian@cline.sh>
 * @see https://docs.cline.sh/forrst/
 */
final class ContactData extends Data
{
    /**
     * Create a new contact information instance.
     *
     * @param null|string $name  The name of the contact person, team, or organization responsible
     *                           for the API. Used for identification in documentation and support
     *                           communications (e.g., "API Team", "John Smith").
     * @param null|string $url   The URL to a web page, documentation site, or contact form where
     *                           additional information can be found or support requests can be submitted.
     *                           Must be a valid HTTP/HTTPS URL.
     * @param null|string $email The email address for contacting the API team or responsible individual.
     *                           Used for support inquiries, bug reports, and general communication.
     *                           Should be a monitored email address.
     *
     * @throws FieldExceedsMaxLengthException if name or email exceeds max length
     * @throws HtmlNotAllowedException        if name contains HTML tags
     * @throws InvalidEmailException          if email format is invalid
     * @throws InvalidProtocolException       if URL doesn't use HTTP/HTTPS or uses unsafe protocol
     * @throws InvalidUrlException            if URL format is invalid
     * @throws MissingRequiredFieldException  if no fields are provided
     * @throws WhitespaceOnlyException        if name is empty or whitespace only
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $url = null,
        public readonly ?string $email = null,
    ) {
        $this->validateContact();
    }

    /**
     * Create contact data with normalized inputs.
     *
     * Normalizes inputs before construction:
     * - Trims whitespace from name and email
     * - Converts email to lowercase (case-insensitive per RFC 5321)
     * - Removes trailing slashes from URLs
     *
     * @param null|string $name  Contact name
     * @param null|string $url   Contact URL
     * @param null|string $email Contact email
     */
    public static function create(
        ?string $name = null,
        ?string $url = null,
        ?string $email = null,
    ): self {
        return new self(
            name: $name !== null ? mb_trim($name) : null,
            url: $url !== null ? mb_rtrim($url, '/') : null,
            email: $email !== null ? mb_strtolower(mb_trim($email)) : null,
        );
    }

    /**
     * Create contact with email only.
     *
     * @param string $email Contact email address
     */
    public static function email(string $email): self
    {
        return self::create(email: $email);
    }

    /**
     * Create contact with name and email.
     *
     * @param string $name  Contact name
     * @param string $email Contact email address
     */
    public static function person(string $name, string $email): self
    {
        return self::create(name: $name, email: $email);
    }

    /**
     * Create contact with all information.
     *
     * @param string $name  Contact name
     * @param string $url   Contact URL
     * @param string $email Contact email address
     */
    public static function full(string $name, string $url, string $email): self
    {
        return self::create(name: $name, url: $url, email: $email);
    }

    /**
     * Create contact for a team.
     *
     * @param string $teamName Team name
     * @param string $url      Team documentation or support URL
     * @param string $email    Team email address
     */
    public static function team(string $teamName, string $url, string $email): self
    {
        return self::create(name: $teamName, url: $url, email: $email);
    }

    /**
     * Validate contact information fields.
     *
     * @throws FieldExceedsMaxLengthException
     * @throws HtmlNotAllowedException
     * @throws InvalidEmailException
     * @throws InvalidProtocolException
     * @throws InvalidUrlException
     * @throws MissingRequiredFieldException
     * @throws WhitespaceOnlyException
     */
    private function validateContact(): void
    {
        if ($this->name !== null) {
            $this->validateName();
        }

        if ($this->url !== null) {
            $this->validateUrl();
        }

        if ($this->email !== null) {
            $this->validateEmail();
        }

        $this->validateAtLeastOneFieldPresent();
    }

    /**
     * Validate the name field.
     *
     * @throws FieldExceedsMaxLengthException
     * @throws HtmlNotAllowedException
     * @throws WhitespaceOnlyException
     */
    private function validateName(): void
    {
        $trimmedName = mb_trim((string) $this->name);

        if ($trimmedName === '') {
            throw WhitespaceOnlyException::forField('name');
        }

        if (mb_strlen($trimmedName) > 255) {
            throw FieldExceedsMaxLengthException::forField('name', 255);
        }

        // Prevent HTML/script injection
        if ($trimmedName !== strip_tags($trimmedName)) {
            throw HtmlNotAllowedException::forField('name');
        }
    }

    /**
     * Validate the URL field.
     *
     * @throws InvalidProtocolException
     * @throws InvalidUrlException
     */
    private function validateUrl(): void
    {
        if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
            throw InvalidUrlException::invalidFormat('url');
        }

        $parsedUrl = parse_url((string) $this->url);

        if (!isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'], true)) {
            throw InvalidProtocolException::forUrl('url');
        }

        // Prevent javascript: and data: URLs
        $scheme = mb_strtolower($parsedUrl['scheme']);

        if (in_array($scheme, ['javascript', 'data', 'vbscript', 'file'], true)) {
            throw InvalidProtocolException::forUrl('url');
        }
    }

    /**
     * Validate the email field.
     *
     * @throws FieldExceedsMaxLengthException
     * @throws InvalidEmailException
     */
    private function validateEmail(): void
    {
        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw InvalidEmailException::forField('email');
        }

        // Additional RFC 5322 validation
        if (mb_strlen((string) $this->email) > 254) {
            throw FieldExceedsMaxLengthException::forField('email', 254);
        }
    }

    /**
     * Validate that at least one contact method is provided.
     *
     * @throws MissingRequiredFieldException
     */
    private function validateAtLeastOneFieldPresent(): void
    {
        if ($this->name === null && $this->url === null && $this->email === null) {
            throw MissingRequiredFieldException::forField('name, url, or email');
        }
    }
}
