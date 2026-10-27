<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Support\FocalPoint;
use PHPUnit\Framework\TestCase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class FocalPointTest extends TestCase
{
    public function test_a_photo_without_a_point_is_framed_in_the_middle(): void
    {
        $this->assertSame(['x' => 50, 'y' => 50], FocalPoint::of(null));
        $this->assertSame(['x' => 50, 'y' => 50], FocalPoint::of(new Media));
        $this->assertSame('object-position: 50% 50%', FocalPoint::style(null));
    }

    public function test_the_point_is_kept_between_0_and_100(): void
    {
        $photo = new Media(['custom_properties' => []]);

        FocalPoint::set($photo, ['x' => 20, 'y' => 140]);
        $this->assertSame(['x' => 20, 'y' => 100], $photo->getCustomProperty('focus'));
        $this->assertSame('object-position: 20% 100%', FocalPoint::style($photo));

        $this->assertSame('object-position: 0% 50%', FocalPoint::css(-5, null));
    }

    public function test_the_middle_leaves_no_property_behind(): void
    {
        $photo = new Media(['custom_properties' => ['focus' => ['x' => 10, 'y' => 10], 'alt' => 'Miska']]);

        FocalPoint::set($photo, ['x' => 50, 'y' => 50]);

        $this->assertSame(['alt' => 'Miska'], $photo->custom_properties);
    }
}
