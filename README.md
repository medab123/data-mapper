# 📦 DataMapper

A reusable **data mapping and transformation** library for Laravel 12. Maps source fields to target fields with chained value transformers, dot-notation and wildcard field extraction, value mapping lookups, and full support for both associative and indexed (header-based) data rows.

> **Namespace:** `Medox\DataMapper`  
> **Requires:** PHP 8.4+ · Laravel 12 · `spatie/laravel-data` ^4.19

---

## 📖 Table of Contents

- [Installation](#-installation)
- [Architecture](#-architecture)
- [Quick Start](#-quick-start)
- [Core Components](#-core-components)
- [Built-in Transformers](#-built-in-transformers)
- [Transformer Groups](#-transformer-groups)
- [Column Matching](#-column-matching)
- [Field Extraction](#-field-extraction)
- [Value Mapping](#-value-mapping)
- [Creating Custom Transformers](#-creating-custom-transformers)
- [DTOs](#-dtos)
- [Contracts](#-contracts)
- [Testing](#-testing)
- [License](#-license)

---

## 🚀 Installation

### From GitHub

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/medab123/data-mapper.git" }
    ],
    "require": {
        "medox/data-mapper": "^0.2"
    }
}
```

### As a local Composer package

In your root `composer.json`, add the package as a path repository:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/data-mapper",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "medox/data-mapper": "@dev"
    }
}
```

Then install:

```bash
composer update medox/data-mapper
```

The `DataMapperServiceProvider` is auto-discovered by Laravel. It registers:
- `DataMapperInterface` → `DataMapperService` (binding)
- `ValueTransformer` — singleton with the 10 built-in transformers in the `core` group, plus anything declared in `config/data-mapper.php`
- `NormalizerInterface` — singleton, from `data-mapper.normalizer`
- `ColumnMatcherInterface` — singleton, from `data-mapper.column_matcher`
- `ValueMatcherInterface` — singleton, from `data-mapper.value_matcher`
- `FieldExtractor` — singleton, wired to that matcher

To register your own transformers, publish the config:

```bash
php artisan vendor:publish --tag=data-mapper-config
```

---

## 🏗 Architecture

```
src/
├── DataMapperService.php          # Main entry point — maps data rows using rules
├── DataMapperServiceProvider.php  # Laravel auto-discovery provider
├── FieldExtractor.php             # Dot-notation & wildcard field extraction
├── ValueTransformer.php           # Transformer registry & value transformation engine
│
├── Contracts/
│   ├── DataMapperInterface.php    # Main service contract
│   └── TransformerInterface.php   # Interface for all transformers
│
├── DTO/
│   ├── MappingConfigurationData.php  # Input: data + rules + headers
│   ├── MappingRuleData.php           # Single mapping rule definition
│   └── DataMappingResultData.php     # Output: mapped data + errors
│
└── Transformers/                  # 10 built-in transformers
    ├── NoneTransformer.php
    ├── TrimTransformer.php
    ├── UpperTransformer.php
    ├── LowerTransformer.php
    ├── IntegerTransformer.php
    ├── FloatTransformer.php
    ├── BooleanTransformer.php
    ├── DateTransformer.php
    ├── ArrayFirstTransformer.php
    └── ArrayJoinTransformer.php
```

---

## ⚡ Quick Start

```php
use Medox\DataMapper\Contracts\DataMapperInterface;
use Medox\DataMapper\DTO\MappingConfigurationData;
use Medox\DataMapper\DTO\MappingRuleData;
use Spatie\LaravelData\DataCollection;

$mapper = app(DataMapperInterface::class);

$config = new MappingConfigurationData(
    data: [
        ['name' => 'John Doe', 'email' => 'john@example.com', 'age' => '30'],
        ['name' => 'Jane Smith', 'email' => 'jane@example.com', 'age' => '25'],
    ],
    mappingRules: MappingRuleData::collection([
        new MappingRuleData(
            sourceField: 'name',
            targetField: 'full_name',
            transformation: 'trim',
        ),
        new MappingRuleData(
            sourceField: 'email',
            targetField: 'contact_email',
            transformation: 'lower',
        ),
        new MappingRuleData(
            sourceField: 'age',
            targetField: 'user_age',
            transformation: 'int',
        ),
    ]),
);

$result = $mapper->map($config);

// $result->data = [
//     ['full_name' => 'John Doe', 'contact_email' => 'john@example.com', 'user_age' => 30],
//     ['full_name' => 'Jane Smith', 'contact_email' => 'jane@example.com', 'user_age' => 25],
// ]
// $result->errors = []
```

---

## 🧩 Core Components

### `DataMapperService`

The main service class. Implements `DataMapperInterface`.

```php
public function map(MappingConfigurationData $config): DataMappingResultData
```

**Behaviour:**
- Automatically detects whether rows are **associative** (`['name' => 'John']`) or **indexed** (`['John', 'john@example.com']`)
- For indexed rows, uses the `headers` array to resolve field positions
- Wraps each row in a try/catch — failed rows are captured as errors, not exceptions
- Returns `DataMappingResultData` with mapped data and any per-row errors

### `FieldExtractor`

Extracts values from data using:

| Pattern | Example | Description |
|---|---|---|
| **Direct access** | `name` | Top-level field |
| **Dot notation** | `address.city` | Nested field traversal |
| **Wildcard** | `items.*.name` | Extract from all array elements |

```php
$extractor = app(FieldExtractor::class);

$data = [
    'user' => ['profile' => ['name' => 'John']],
    'items' => [
        ['name' => 'A', 'price' => 10],
        ['name' => 'B', 'price' => 20],
    ],
];

$extractor->extractValue($data, 'user.profile.name');  // 'John'
$extractor->extractArrayValues($data, 'items.*.name'); // ['A', 'B']
$extractor->hasField($data, 'user.profile.name');       // true
```

### `ValueTransformer`

The transformer registry and execution engine. Manages all registered transformers and applies transformation chains.

```php
$transformer = app(ValueTransformer::class);

// Every transformer, in every group
$transformer->getTransformerOptions(); // ['none' => 'None', 'trim' => 'Trim', ...]

// Only the ones in the given groups
$transformer->getTransformerOptions(ValueTransformer::GROUP_CORE, 'export');

// Register a custom transformer (defaults to the "core" group)
$transformer->registerTransformer(new MyCustomTransformer());
```

See [Transformer Groups](#-transformer-groups) for narrowing what a given screen offers.

**Transformation flow:**
1. Check if value is empty → return `defaultValue` (unless it's an array transformer)
2. Apply **value mapping** if configured (lookup table)
3. Apply **transformer** (type conversion, formatting)
4. If result is empty string and `defaultValue` is set → return `defaultValue`

---

## 🔧 Built-in Transformers

| Name | Label | Description | Requires Format |
|---|---|---|:---:|
| `none` | None | Pass-through, no transformation | ❌ |
| `trim` | Trim | Remove leading/trailing whitespace | ❌ |
| `upper` | Uppercase | Convert to UPPERCASE | ❌ |
| `lower` | Lowercase | Convert to lowercase | ❌ |
| `int` | Integer | Plain `(int)` cast — see the note below | ❌ |
| `float` | Float | Cast to `float` with precision control | ✅ (decimals) |
| `bool` | Boolean | Cast to `bool` (handles `"true"`, `"1"`, `"yes"`, etc.) | ❌ |
| `date` | Date | Parse to `?DateTimeImmutable`; unparseable yields `null`, never throws | ✅ (date format) |
| `array_first` | Array First | Extract first element from array | ❌ |
| `array_join` | Array Join | Join array elements with separator | ✅ (separator) |


> **`int` is a plain cast, on purpose.** PHP takes the leading numeric run and discards
> the rest, so `'41,000'` is `41` and `'$31,500'` is `0`. Working out what number the
> source *meant* would mean guessing what a comma is for — it groups thousands in one
> locale and marks a decimal in another — so this transformer does not try. A source that
> writes numbers with separators or symbols wants a format-aware transformer, where the
> convention is stated rather than assumed. (`float` currently strips non-numeric
> characters, which handles `'$31,500'` but concatenates every digit in
> `'$31,500 (was $35,000)'` — a known bug, not a feature to rely on.)

---

## 👥 Transformer Groups

The ten built-in transformers are type-level operations — `trim`, `int`, `date` — that mean
the same thing everywhere. Anything carrying **business meaning** belongs to your application,
not to this package. Groups are how you keep it there: you name a group, register your own
transformers into it, and ask for the groups a given screen should offer.

The package never interprets a group name. `import`, `export`, `syndication`, `crm` — it only
answers which transformers are in one.

### Declaring groups in config

Publish the config and list your classes under the group they belong to:

```bash
php artisan vendor:publish --tag=data-mapper-config
```

```php
// config/data-mapper.php
return [
    'groups' => [
        'import' => [
            App\Import\Transformers\StockNumberTransformer::class,
        ],
        'export' => [
            App\Export\Transformers\TransmissionExpandTransformer::class,
        ],
    ],
];
```

Each class is resolved through the container, so it may declare its own dependencies. A class
that does not implement `TransformerInterface` throws at boot rather than silently doing
nothing halfway through a feed.

### Registering groups at runtime

```php
$valueTransformer = app(ValueTransformer::class);

// A whole group at once
$valueTransformer->registerGroup('export', [new TransmissionExpandTransformer]);

// One transformer, into one or more groups
$valueTransformer->registerTransformer(new SlugTransformer, 'import', 'export');

// Widen a group with transformers that are already registered
$valueTransformer->addToGroup('export', 'trim', 'upper');
```

### Letting a transformer choose its own group

Implement `GroupedTransformerInterface` and the registration site no longer has to repeat the
group name:

```php
use Medox\DataMapper\Contracts\GroupedTransformerInterface;

final class TransmissionExpandTransformer implements GroupedTransformerInterface
{
    public function getGroups(): array
    {
        return ['export'];
    }

    // ... getName(), getLabel(), getDescription(), transform(), requiresFormat()
}
```

A group passed explicitly to `registerTransformer()` — or listed in config — always wins over
the one the class declares.

### Querying groups

```php
// What a UI should offer
$valueTransformer->getTransformerOptions(ValueTransformer::GROUP_CORE);            // built-ins only
$valueTransformer->getTransformerOptions(ValueTransformer::GROUP_CORE, 'export');  // built-ins + export
$valueTransformer->getTransformerOptions();                                        // everything

// Introspection
$valueTransformer->getGroups();                              // ['core', 'import', 'export']
$valueTransformer->getGroupsFor('transmission_expand');      // ['export']
$valueTransformer->hasGroup('export');                       // true
$valueTransformer->getTransformersInGroups('export');        // array<string, TransformerInterface>

// Lookups can require a group
$valueTransformer->hasTransformer('transmission_expand', 'export');   // true
$valueTransformer->hasTransformer('transmission_expand', 'core');     // false
```

> **Groups describe what a UI should offer, not what a pipeline may run.**
> `ValueTransformer::transform()` resolves a rule's transformer by name across every group, so
> a stored mapping keeps working even if a screen would no longer offer that transformer.

---

## 🔤 Column Matching

A mapping rule names a column — `dealer id`, `stocknumber`. The source exports whatever it
exports — `Dealer ID`, `StockNumber`. With a byte-exact lookup those are different columns
and the value comes back `null`, **with no error**, because a rule that is not required is
allowed to find nothing. A configuration can name twenty columns, match none of them, and
map blank rows while reporting success.

`RelaxedColumnMatcher` is the default. An exact key always wins; only when none exists is
the name compared with letter case, surrounding whitespace and a byte-order mark folded
away:

```php
$row = ['Dealer ID' => 7, "\u{FEFF}StockNumber" => 'A1'];

$extractor->extractValue($row, 'dealer id');    // 7
$extractor->extractValue($row, 'stocknumber');  // 'A1'
```

It works the same for positional headers (`headers: ['VIN', 'Dealer ID']` with list-shaped
rows) and for every segment of a nested path (`vehicle.engine.cylinders` finds
`Vehicle.Engine.Cylinders`).

**Why this is safe to have on:** the relaxed pass runs only when no exact key exists, so it
can never change a lookup that already found a value — it can only rescue one that found
nothing. A source carrying both `price` and `Price` keeps them apart for whichever one was
configured.

**What it will not do:** guess across separators. `stock_number` and `Stock Number` stay
different columns, because that would start mapping fields nobody asked for.

### Choosing a policy

```php
// config/data-mapper.php
'column_matcher' => Medox\DataMapper\ColumnMatchers\RelaxedColumnMatcher::class, // default
// 'column_matcher' => Medox\DataMapper\ColumnMatchers\ExactColumnMatcher::class, // byte-for-byte
```

### The normalizer is one decision, shared

What counts as formatting is configured **once** and used by both column matching and
[value mapping](#-value-mapping), so the two can never drift apart:

```php
// config/data-mapper.php
'normalizer' => Medox\DataMapper\Normalizers\FormattingNormalizer::class, // default
```

To move the line, implement `NormalizerInterface`:

```php
final class SeparatorInsensitiveNormalizer implements NormalizerInterface
{
    public function normalize(string $value): string
    {
        return str_replace([' ', '_', '-'], '', mb_strtolower(trim($value)));
    }
}
```

Results are cached, so an implementation must be a pure function of its argument. If only
*column names* need a rule of their own, `RelaxedColumnMatcher::normalize()` is still
`protected` and overridable. Anything implementing `ColumnMatcherInterface` is also
accepted, if folding a name is not how your sources work at all.

> Resolve `DataMapperService` from the container and the configured matcher is used for
> both keyed rows and positional headers. Construct it by hand and you must pass the same
> matcher to `FieldExtractor` and to `DataMapperService` yourself.

---

## 🗺 Field Extraction

### Dot Notation

Access nested fields in associative arrays:

```php
// Source data
['address' => ['street' => '123 Main St', 'city' => 'NYC']]

// Mapping rule: sourceField = 'address.city'
// Extracted value: 'NYC'
```

### Wildcard Notation

Extract values from arrays of objects:

```php
// Source data
['images' => [
    ['url' => 'img1.jpg', 'alt' => 'First'],
    ['url' => 'img2.jpg', 'alt' => 'Second'],
]]

// Mapping rule: sourceField = 'images.*.url'
// Extracted value: ['img1.jpg', 'img2.jpg']
```

### Indexed (Header-Based) Rows

For data without keys (e.g., CSV rows), provide headers:

```php
$config = new MappingConfigurationData(
    data: [
        ['John', 'john@example.com', '30'],
        ['Jane', 'jane@example.com', '25'],
    ],
    mappingRules: MappingRuleData::collection([
        new MappingRuleData(sourceField: 'name', targetField: 'full_name'),
        new MappingRuleData(sourceField: 'email', targetField: 'contact'),
    ]),
    headers: ['name', 'email', 'age'],
);
```

---

## 🔀 Value Mapping

Map specific values using a lookup table. Useful for code-to-label conversions:

```php
new MappingRuleData(
    sourceField: 'condition_code',
    targetField: 'condition',
    // transformation omitted: null means no transformation
    valueMapping: [
        ['from' => '0', 'to' => 'Used'],
        ['from' => '1', 'to' => 'New'],
        ['from' => '2', 'to' => 'Refurbished'],
    ],
);

// Input: '1' → Output: 'New'
// Input: '0' → Output: 'Used'
// Input: '99' → Output: '99' (unmapped values pass through)
```

Value mapping is applied **before** the transformer, so you can combine both:

```php
new MappingRuleData(
    sourceField: 'status',
    targetField: 'display_status',
    transformation: 'upper',
    valueMapping: [['from' => '1', 'to' => 'active'], ['from' => '0', 'to' => 'inactive']],
);
// Input: '1' → mapped to 'active' → transformed to 'ACTIVE'
```

### How loosely a value matches

A mapping is a statement about what a source's value **means**, and `" Used"` does not mean
something different from `"Used"`. With a byte-exact lookup it did: a hand-written
`Used → used` silently missed every row a provider happened to send as `USED`, the value
reached the target un-normalised, and the field then failed its cast for the whole file.

`RelaxedValueMatcher` is the default. An exact key always wins; only when none exists is the
value compared through the [normalizer](#-column-matching):

```php
valueMapping: [['from' => 'Used', 'to' => 'used']]

// 'USED'      → 'used'
// '  used  '  → 'used'
// 'Salvage'   → 'Salvage'   (no rule matches: passed through unchanged)
```

**Colliding keys are dropped, not shadowed.** If you write both `Used → second-hand` and
`USED → pre-owned`, you plainly meant them apart — you typed both, in one table. Each still
answers for its own exact spelling, but a third spelling matching neither gets no answer
rather than an arbitrary winner. This is the deliberate difference from column matching,
where colliding keys are an accident of the data and the first one wins.

**Only strings and integers are mapped.** `null` means absent, not "matches the empty
key"; an array is a multi-value extraction and is mapped element by element; and folding a
float or bool into a key would invent a numeric equivalence that belongs to a transformer,
not to a lookup.

```php
// config/data-mapper.php
'value_matcher' => Medox\DataMapper\ValueMatchers\RelaxedValueMatcher::class, // default
// 'value_matcher' => Medox\DataMapper\ValueMatchers\ExactValueMatcher::class, // byte-for-byte
```

---

## 🛠 Creating Custom Transformers

Implement the `TransformerInterface`:

```php
use Medox\DataMapper\Contracts\TransformerInterface;

final class SlugTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'slug';
    }

    public function getLabel(): string
    {
        return 'Slugify';
    }

    public function getDescription(): string
    {
        return 'Converts text to URL-friendly slug';
    }

    public function transform($value, ?string $format = null, $defaultValue = null)
    {
        if ($value === null) {
            return $defaultValue;
        }

        return \Illuminate\Support\Str::slug((string) $value);
    }

    public function requiresFormat(): bool
    {
        return false;
    }
}
```

Register it:

```php
$transformer = app(ValueTransformer::class);
$transformer->registerTransformer(new SlugTransformer());
```

Or register in a service provider for global availability:

```php
public function boot(): void
{
    $this->app->make(ValueTransformer::class)
        ->registerTransformer(new SlugTransformer());
}
```

A transformer that carries business meaning should go in a group of its own rather than in
`core` — see [Transformer Groups](#-transformer-groups). The declarative route is
`config/data-mapper.php`:

```php
'groups' => [
    'import' => [App\Import\Transformers\SlugTransformer::class],
],
```

---

## 📋 DTOs

### `MappingConfigurationData`

Input to the mapper:

| Property | Type | Description |
|---|---|---|
| `data` | `array` | Array of rows to map |
| `mappingRules` | `DataCollection<MappingRuleData>` | Mapping rules to apply |
| `headers` | `?array` | Column headers for indexed rows |

### `MappingRuleData`

A single field mapping rule:

| Property | Type | Default | Description |
|---|---|---|---|
| `sourceField` | `string` | — | Source field name (supports dot/wildcard notation) |
| `targetField` | `string` | — | Target field name in output |
| `transformation` | `?string` | `null` | Transformer name to apply; `null` means none |
| `isRequired` | `bool` | `false` | Throw if source field is missing |
| `defaultValue` | `mixed` | `null` | Fallback for empty values |
| `format` | `?string` | `null` | Format parameter for transformers (e.g., date format) |
| `valueMapping` | `?array` | `null` | Value lookup table (`[['from' => ..., 'to' => ...]]`) |

> A rule naming no transformation carries `null`, not `'none'`. Both mean "leave the
> value alone", and `null` is the shape a form already produces — an empty string that
> `ConvertEmptyStringsToNull` turns into null before it is stored. `transform()` skips the
> transformer lookup on null. An unregistered name behaves the same way.

### `DataMappingResultData`

Output from the mapper:

| Property | Type | Description |
|---|---|---|
| `data` | `array` | Successfully mapped rows |
| `errors` | `array` | Per-row error messages |

---

## 📜 Contracts

### `DataMapperInterface`

```php
interface DataMapperInterface
{
    public function map(MappingConfigurationData $config): DataMappingResultData;
}
```

### `TransformerInterface`

```php
interface TransformerInterface
{
    public function getName(): string;
    public function getLabel(): string;
    public function getDescription(): string;
    public function transform($value, ?string $format = null, $defaultValue = null);
    public function requiresFormat(): bool;
}
```


### `ColumnMatcherInterface`

```php
interface ColumnMatcherInterface
{
    public function matchKey(array $row, string $column): string|int|null;
    public function matchIndex(array $headers, string $column): ?int;
}
```

### `ValueMatcherInterface`

```php
interface ValueMatcherInterface
{
    public function matchValue(mixed $value, array $valueMapping): mixed;
}
```

### `NormalizerInterface`

```php
interface NormalizerInterface
{
    public function normalize(string $value): string;
}
```

### `GroupedTransformerInterface`

Optional. Extends `TransformerInterface` with a single method so a transformer can name the
groups it belongs to:

```php
/** @return array<int, string> */
public function getGroups(): array;
```

An explicit group at the registration site, or in `config/data-mapper.php`, overrides it.

---

## 🧪 Testing

```bash
# From the package directory
./vendor/bin/phpunit

# From the root project
php artisan test
```

---

## 📦 Dependencies

| Package | Version | Purpose |
|---|---|---|
| `illuminate/support` | ^12.0 | Laravel framework support |
| `spatie/laravel-data` | ^4.19 | Typed DTOs with auto-mapping |

---

## 📄 License

MIT
