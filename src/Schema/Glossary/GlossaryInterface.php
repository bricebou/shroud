<?php

declare(strict_types=1);

namespace Shroud\Schema\Glossary;

use Shroud\Schema\DraftRule;

/**
 * Names the columns of a language, or of a trade, so that a draft recognises what they hold.
 *
 * A glossary lists rules on the names of the columns: `email` holds an email, `birth_date` a
 * date to shift, `password` a secret to clear. The draft tries the glossaries it is given in
 * order, after the rules of the platform and the ids.
 */
interface GlossaryInterface
{
    /**
     * Lists the rules of the glossary, in order of trial.
     *
     * @return list<DraftRule>
     */
    public function rules(): array;
}
