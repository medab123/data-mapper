# 📦 Elaitech DataMapper

A reusable **data mapping and transformation** library for Laravel 12. Maps source fields to target fields with chained value transformers, dot-notation and wildcard field extraction, value mapping lookups, and full support for both associative and indexed (header-based) data rows.

> **Namespace:** `Elaitechx\DataMapper`  
> **Requires:** PHP 8.4+ · Laravel 12 · `spatie/laravel-data` ^4.19

---

## 📖 Table of Contents

- [Installation](#-installation)
- [Architecture](#-architecture)
- [Quick Start](#-quick-start)
- [Core Components](#-core-components)
- [Built-in Transformers](#-built-in-transformers)
- [Field Extraction](#-field-extraction)
- [Value Mapping](#-value-mapping)
- [Creating Custom Transformers](#-creating-custom-transformers)
- [DTOs](#-dtos)
- [Contracts](#-contracts)
- [Testing](#-testing)
- [License](#-license)

---

## 🚀 Installation

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
        "elaitech/data-mapper": "@dev"
    }
}
```

Then install:

```bash
composer update elaitech/data-mapper
```

The `DataMapperServiceProvider` is auto-discovered by Laravel. It registers:
- `DataMapperInterface` → `DataMapperService` (binding)
- `ValueTransformer` — singleton with 10 built-in transformers
- `FieldExtractor` — singleton

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
use Elaitech\DataMapper\Contracts\DataMapperInterface;
use Elaitech\DataMapper\DTO\MappingConfigurationData;
use Elaitech\DataMapper\DTO\MappingRuleData;
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
            transformation: 'integer',
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

// Check available transformers
$transformer->getTransformerOptions(); // ['none' => 'None', 'trim' => 'Trim', ...]

// Register a custom transformer
$transformer->registerTransformer(new MyCustomTransformer());
```

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
| `integer` | Integer | Cast to `int` | ❌ |
| `float` | Float | Cast to `float` with precision control | ✅ (decimals) |
| `boolean` | Boolean | Cast to `bool` (handles `"true"`, `"1"`, `"yes"`, etc.) | ❌ |
| `date` | Date | Parse and reformat dates | ✅ (date format) |
| `array_first` | Array First | Extract first element from array | ❌ |
| `array_join` | Array Join | Join array elements with separator | ✅ (separator) |

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
    transformation: 'none',
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

---

## 🛠 Creating Custom Transformers

Implement the `TransformerInterface`:

```php
use Elaitech\DataMapper\Contracts\TransformerInterface;

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
| `transformation` | `string` | `'none'` | Transformer name to apply |
| `isRequired` | `bool` | `false` | Throw if source field is missing |
| `defaultValue` | `mixed` | `null` | Fallback for empty values |
| `format` | `?string` | `null` | Format parameter for transformers (e.g., date format) |
| `valueMapping` | `?array` | `null` | Value lookup table (`[['from' => ..., 'to' => ...]]`) |

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
