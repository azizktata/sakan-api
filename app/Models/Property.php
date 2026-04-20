<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'title',
        'description',
        'price',
        'transaction_type',
        'property_type',
        'status',
        'location_id',
        'address',
        'latitude',
        'longitude',
        'surface',
        'bedrooms',
        'bathrooms',
        'floor',
        'is_furnished',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_furnished' => 'boolean',
            'price' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function images()
    {
        return $this->hasMany(PropertyImage::class)->orderBy('position');
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class);
    }

    protected function latitude(): Attribute
    {
        return Attribute::get(
            fn ($value) => $value ?? $this->location?->latitude
        );
    }

    protected function longitude(): Attribute
    {
        return Attribute::get(
            fn ($value) => $value ?? $this->location?->longitude
        );
    }
}
