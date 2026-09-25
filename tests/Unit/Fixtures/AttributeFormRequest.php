<?php

declare(strict_types=1);

namespace Ironflow\Tests\Unit\Fixtures;

use Ironflow\Http\FormRequest;
use Ironflow\Validation\Attributes\Confirmed;
use Ironflow\Validation\Attributes\Email;
use Ironflow\Validation\Attributes\In;
use Ironflow\Validation\Attributes\Max;
use Ironflow\Validation\Attributes\Nullable;
use Ironflow\Validation\Attributes\Required;
use Ironflow\Validation\Attributes\Rule;
use Ironflow\Validation\Attributes\StringType;

class AttributeFormRequest extends FormRequest
{
    #[Required, StringType, Max(255)]
    public string $title;

    #[Required, Email]
    public string $email;

    #[Required, In(['draft', 'published'])]
    public string $status;

    #[Nullable, Rule('min:8')]
    public ?string $password = null;

    #[Confirmed]
    public string $newPassword;

    /** No validation attribute — must not appear in the resolved rules. */
    public string $internalTrackingId;

    public function rules(): array
    {
        return $this->rulesFromAttributes();
    }
}
