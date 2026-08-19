<?php

namespace Tests\Feature;

use App\Models\Benefit;
use App\Models\Contact;
use App\Models\ExpectedVolume;
use App\Models\Faq;
use App\Models\VenueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: class-string<Model>, 1: array<string, mixed>}> */
    public static function catalogModels(): array
    {
        return [
            'Benefit' => [Benefit::class, ['name' => 'Lanche', 'slug' => 'lanche-'.uniqid(), 'status' => 'active']],
            'Contact' => [Contact::class, ['name' => 'Suporte', 'link' => 'mailto:suporte@zunmoto.com.br', 'status' => 'active']],
            'ExpectedVolume' => [ExpectedVolume::class, ['name' => 'Tranquilo', 'slug' => 'tranquilo-'.uniqid(), 'status' => 'active']],
            'VenueType' => [VenueType::class, ['name' => 'Pizzaria', 'slug' => 'pizzaria-'.uniqid(), 'status' => 'active']],
            'Faq' => [Faq::class, ['name' => 'Como funciona?', 'description' => 'Resposta de teste.', 'status' => 'active']],
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('catalogModels')]
    public function test_deleting_soft_deletes_instead_of_removing_the_row(string $modelClass, array $attributes): void
    {
        $record = $modelClass::create($attributes);

        $record->delete();

        $this->assertNull($modelClass::find($record->getKey()), 'não deve aparecer na query padrão');
        $this->assertNotNull($modelClass::withTrashed()->find($record->getKey()), 'a linha continua no banco');
        $this->assertNotNull($modelClass::withTrashed()->find($record->getKey())->deleted_at);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('catalogModels')]
    public function test_soft_deleted_record_can_be_restored(string $modelClass, array $attributes): void
    {
        $record = $modelClass::create($attributes);
        $record->delete();

        $modelClass::withTrashed()->find($record->getKey())->restore();

        $restored = $modelClass::find($record->getKey());
        $this->assertNotNull($restored, 'volta a aparecer na query padrão depois de restaurar');
        $this->assertNull($restored->deleted_at);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('catalogModels')]
    public function test_active_scope_excludes_soft_deleted_records(string $modelClass, array $attributes): void
    {
        $record = $modelClass::create($attributes);
        $record->delete();

        $keyName = $record->getKeyName();

        $this->assertSame(0, $modelClass::active()->where($keyName, $record->getKey())->count());
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('catalogModels')]
    public function test_force_delete_removes_the_row_for_good(string $modelClass, array $attributes): void
    {
        $record = $modelClass::create($attributes);
        $record->delete();

        $modelClass::withTrashed()->find($record->getKey())->forceDelete();

        $this->assertNull($modelClass::withTrashed()->find($record->getKey()));
    }
}
