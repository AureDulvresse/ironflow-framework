# Validation

## Quick validation from a request

```php
$data = $request->validate([
    'title' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
]);
```

Throws `Validation\ValidationException` on failure, caught centrally by
`Exceptions\Handler`: a JSON request gets
`422 {"message": "...", "errors": {...}}`; an HTML request gets redirected
back with `_errors`/`_old_input` flashed to the session (picked up by the
`flash-errors` alias → `ShareErrorsFromSession` middleware — see
[Middleware](middleware.md)).

## `FormRequest` — reusable, typed rule sets

See [Requests & Responses](http.md#formrequest--typed-auto-validated-input)
for the full picture — the Router validates it for you before your
controller method runs.

## Manual validation

```php
use Ironflow\Validation\ValidatorFactory;

$validator = (new ValidatorFactory())->make($data, $rules, $messages);

if ($validator->fails()) {
    $errors = $validator->errors();     // ['field' => ['message', ...], ...]
} else {
    $validated = $validator->validated();
}
```

`ValidatorFactory` resolves a `Database\Connection` for the `unique`/`exists`
rules — with no DB configured, those two rules simply pass (they degrade
gracefully rather than crashing a validator built outside an HTTP context).

## Rule reference

Rules are pipe-separated per field: `'title' => 'required|string|max:255'`
(or an array of rule strings). A field marked `nullable` skips every other
rule when its value is `null` or `''`.

**Core**

| Rule | Checks |
|---|---|
| `required` | present and non-empty (an `UploadedFile` must be `isValid()`) |
| `email` | valid email address |
| `string` / `int` / `integer` / `numeric` / `boolean` | type |
| `url` | valid URL |
| `date` | parseable by `strtotime()` |
| `confirmed` | `{field}_confirmation` matches |
| `regex:pattern` | matches a PCRE pattern |
| `in:a,b,c` / `not_in:a,b,c` | value membership |
| `unique:table,column` | no existing row has this value |
| `exists:table,column` | a row with this value exists |
| `nullable` | see above |

**Extended**

`alpha`, `alpha_num`, `alpha_dash`, `uuid`, `ip`, `json`, `array`,
`digits:n`, `starts_with:a,b`, `ends_with:a,b`, `same:field`,
`different:field`, `gt:field`, `gte:field`, `lt:field`, `lte:field`,
`between:min,max`.

**Size** (file-aware — `min`/`max` mean KB for an `UploadedFile`, string
length for a string, numeric comparison otherwise)

`min:n`, `max:n`.

**Files** (value must be an `Http\UploadedFile`)

| Rule | Checks |
|---|---|
| `file` | a valid uploaded file |
| `image` | extension is one of jpg/jpeg/png/gif/bmp/webp/svg |
| `mimes:jpg,png,pdf` | client-declared extension is in the list |
| `mime_types:image/jpeg,...` | detected MIME type is in the list |
| `dimensions:min_width=100,max_width=2000,min_height=...,width=...,height=...` | image dimension constraints |

An unrecognised rule name throws `InvalidArgumentException` immediately —
there's no silent pass-through for a typo'd rule.

## Custom messages

```php
$request->validate(
    ['email' => 'required|email'],
    ['email.required' => 'We need your email address.', 'email' => 'Fallback for any email.* rule.']
);
```

Message lookup order: `"{field}.{rule}"` → `"{field}"` → the built-in
default (French, e.g. *"Le champ email est obligatoire."* — override every
message explicitly if your application needs a different language).

## See also

For a Django-style declarative form — fields defined once as class
properties, rendered *and* validated from the same definition instead of a
raw rules array — see the `ironflow-framework/form-builder` package, built
on top of this validation engine (not part of the framework core).
