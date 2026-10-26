<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\SitePhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SitePhotoTest extends TestCase
{
    public function test_a_photo_from_the_build_an_uploaded_one_and_a_missing_one(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        Storage::disk('public')->putFileAs('studio', UploadedFile::fake()->image('stol.webp'), 'stol.webp');

        $this->assertNotNull(SitePhoto::url('zdjecia/kubek-cappuccino.webp'));
        $this->assertSame(Storage::disk('public')->url('studio/stol.webp'), SitePhoto::url('studio/stol.webp'));
        $this->assertNull(SitePhoto::url('zdjecia/nie-ma-takiego.webp'));
        $this->assertNull(SitePhoto::url('studio/nie-ma-takiego.webp'));
        $this->assertNull(SitePhoto::url(''));
        $this->assertNull(SitePhoto::url(null));
    }
}
