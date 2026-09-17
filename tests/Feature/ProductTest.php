<?php

use App\Models\Business;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->user = User::factory()->admin()->create(['business_id' => $this->business->id]);
    $this->otherBusiness = Business::factory()->create();
    $this->outsider = User::factory()->admin()->create(['business_id' => $this->otherBusiness->id]);
});

function productFor(int $businessId, array $attrs = []): Product
{
    return Product::factory()->create(array_merge(['business_id' => $businessId], $attrs));
}

test('the product list shows only the acting business products', function () {
    productFor($this->business->id, ['name' => 'Ours']);
    productFor($this->otherBusiness->id, ['name' => 'Theirs']);

    $this->actingAs($this->user)
        ->get(route('products.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->where('products', fn ($products) => $products->count() === 1 && $products->first()['name'] === 'Ours'));
});

test('a new product can be added to the catalog', function () {
    $this->actingAs($this->user)
        ->post(route('products.store'), [
            'name' => 'Fresh Item',
            'price' => 10,
            'cost' => 4,
            'stock' => 3,
            'reorder_level' => 2,
        ])
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('success');

    expect(Product::forBusiness($this->business->id)->where('name', 'Fresh Item')->first())
        ->not->toBeNull()
        ->and((int) Product::forBusiness($this->business->id)->where('name', 'Fresh Item')->value('stock'))->toBe(3);
});

test('the edit page renders for the owning business', function () {
    $product = productFor($this->business->id, ['name' => 'Editable', 'price' => 25.5]);

    $this->actingAs($this->user)
        ->get(route('products.edit', $product))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/Edit')
            ->where('product.name', 'Editable')
            ->where('product.price', 25.5));
});

test('a product can be updated without touching on-hand stock', function () {
    $product = productFor($this->business->id, ['name' => 'Before', 'price' => 20, 'stock' => 7]);

    $this->actingAs($this->user)
        ->put(route('products.update', $product), [
            'name' => 'After',
            'price' => 30,
            'condition' => 'new',
        ])
        ->assertRedirect(route('products.index'));

    $product->refresh();
    expect($product->name)->toBe('After')
        ->and((float) $product->price)->toBe(30.0)
        ->and((int) $product->stock)->toBe(7); // omitted stock is left alone
});

test('receiving stock is a standard update that sets a higher on-hand count', function () {
    $product = productFor($this->business->id, ['name' => 'Cable', 'price' => 12, 'stock' => 5]);

    $this->actingAs($this->user)
        ->put(route('products.update', $product), [
            'name' => 'Cable',
            'price' => 12,
            'stock' => 15,
        ])
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('success');

    expect((int) $product->refresh()->stock)->toBe(15);
});

test('a negative stock submission is clamped so on-hand can never go below zero', function () {
    $product = productFor($this->business->id, ['name' => 'Widget', 'price' => 9, 'stock' => 3]);

    $this->actingAs($this->user)
        ->put(route('products.update', $product), [
            'name' => 'Widget',
            'price' => 9,
            'stock' => -100,
        ])
        ->assertSessionHasErrors('stock'); // min:0 rejects negatives outright

    expect((int) $product->refresh()->stock)->toBe(3); // unchanged
});

test('receiving cannot target another business product', function () {
    $foreign = productFor($this->otherBusiness->id, ['name' => 'Theirs', 'price' => 8, 'stock' => 4]);

    $this->actingAs($this->user)
        ->put(route('products.update', $foreign), [
            'name' => 'Theirs',
            'price' => 8,
            'stock' => 99,
        ])
        ->assertStatus(403);

    expect((int) $foreign->refresh()->stock)->toBe(4);
});

test('a product can be removed', function () {
    $product = productFor($this->business->id);

    $this->actingAs($this->user)
        ->delete(route('products.destroy', $product))
        ->assertRedirect(route('products.index'))
        ->assertSessionHas('success');

    expect(Product::find($product->id))->toBeNull();
});

test('other business staff cannot edit, update, or delete a foreign product', function () {
    $product = productFor($this->business->id, ['name' => 'Protected', 'stock' => 9]);

    $this->actingAs($this->outsider)->get(route('products.edit', $product))->assertStatus(403);
    $this->actingAs($this->outsider)->put(route('products.update', $product), ['name' => 'Hacked'])->assertStatus(403);
    $this->actingAs($this->outsider)->delete(route('products.destroy', $product))->assertStatus(403);

    $product->refresh();
    expect($product->name)->toBe('Protected')
        ->and((int) $product->stock)->toBe(9)
        ->and(Product::find($product->id))->not->toBeNull();
});

test('product actions require authentication', function () {
    $product = productFor($this->business->id);

    $this->get(route('products.index'))->assertRedirect(route('login'));
    $this->get(route('products.edit', $product))->assertRedirect(route('login'));
    $this->delete(route('products.destroy', $product))->assertRedirect(route('login'));
});
