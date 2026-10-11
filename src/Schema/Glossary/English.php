<?php

declare(strict_types=1);

namespace Shroud\Schema\Glossary;

use Shroud\Schema\DraftRule;

/**
 * Names the columns in English, the usual language of schemas.
 *
 * The rules go from the surest to the vaguest: secrets to clear, then technical columns to keep,
 * then the personal data a strategy replaces, an exact name without review, an affixed name with.
 */
final class English implements GlossaryInterface
{
    public function rules(): array
    {
        return [
            // Secrets and sensitive data: cleared.
            new DraftRule('/^(pass|password|passwd|pwd|secret|token|hash|salt|api_?key|cookie|ip|ips)$|_(password|secret|token|hash|salt|key|cookie|ip|ips)$|^(password|secret|token|cookie)_/', 'new Clear()'),
            new DraftRule('/(^|_)(iban|bic|swift|card_?number|cc_?number|cvv|ssn|gender|sex|religion|ethnicity)(_|$)/', 'new Clear()'),
            new DraftRule('/(^|_)(log|logs)$/', 'new Clear()', review: true),
            // Dates to shift, before the dates to keep.
            new DraftRule('/birth/', 'new ShiftedDate()'),
            // Technical columns: kept.
            new DraftRule('/^(status|state|lang|locale|type|kind|extension|mime_?type|rank|position|weight|order|active|enabled|visible|public|published|deleted|created|updated|modified|date|date_|timestamp|version|currency|amount|price|total|quantity|country_?code|iso|format|mode)(_|$)/', 'new Keep()'),
            new DraftRule('/(^|_)(uuid|uid|guid|slug|data|metadata|config|configuration|options|settings|params|parameters|prefs|preferences|extra)$/', 'new Keep()', review: true),
            new DraftRule('/(^|_)(latitude|longitude|lat|lng|lon)$/', 'new Keep()', review: true),
            // Personal data: an exact name is sure, an affixed name is to review.
            new DraftRule('/^(email|e_mail|mail)$/', 'new Email()'),
            new DraftRule('/(^|_)(email|e_mail|mail)(_|$)/', 'new Email()', review: true),
            new DraftRule('/^(first_?name|given_?name|forename)$/', 'new FirstName()'),
            new DraftRule('/^(last_?name|surname|family_?name)$/', 'new LastName()'),
            new DraftRule('/^(name|full_?name|display_?name|author|signature)$|_name$/', 'new FullName()', review: true),
            new DraftRule('/^(login|username|user_?name|nickname|handle)$/', 'new Login()'),
            new DraftRule('/^(site_?name|site)$/', 'new ShapedText()'),
            new DraftRule('/(^|_)(url|link|href|website|web_?site|homepage|referer|referrer)(_|$)/', 'new Url(keepHost: false)', review: true),
            new DraftRule('/^(tel|telephone|phone|fax|mobile|cell)(_?\d)?$|_(tel|telephone|phone|fax|mobile)$/', 'new ShapedDigits(keepDiallingCode: true)'),
            new DraftRule('/(^|_)(address|street)(_?\d|_line_?\d)?$/', 'new Street()'),
            new DraftRule('/(^|_)(postal_?code|post_?code|zip|zip_?code)$/', 'new PostalCode()'),
            new DraftRule('/(^|_)(city|town|locality)$/', 'new City()'),
            new DraftRule('/(^|_)(country|state|region|province)$/', 'new Keep()', review: true),
            new DraftRule('/(^|_)(company|organi[sz]ation|employer|institution|firm|affiliation)(_|$)/', 'new Organization()'),
            new DraftRule('/(^|_)(job_?title|occupation|profession)(_|$)/', 'new ShapedText()'),
            new DraftRule('/^(bio|biography|about|user_?agent)$/', 'new ShapedText()'),
            new DraftRule('/(^|_)(vat|vat_?number|tax_?id|ein)(_|$)/', 'new ShapedText()'),
            new DraftRule('/(twitter|facebook|instagram|linkedin|mastodon|github|bluesky)/', 'new ShapedText()', review: true),
        ];
    }
}
