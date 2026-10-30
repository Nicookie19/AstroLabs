<?php

namespace Tests\Unit;

use App\Models\Dataset;
use PHPUnit\Framework\TestCase;

class DatasetLabelsTest extends TestCase
{
    public function test_labels_always_serialize_as_a_list_including_legacy_imports(): void
    {
        foreach ([
            ['["Spiral","Elliptical"]', ['Spiral', 'Elliptical']],
            [json_encode('["Spiral"]'), ['Spiral']],
            [json_encode('[]'), []],
            [null, []],
            ['invalid', []],
        ] as [$stored, $expected]) {
            $dataset = new Dataset();
            $dataset->setRawAttributes(['label_names' => $stored]);
            $this->assertSame($expected, $dataset->label_names);
            $this->assertSame($expected, $dataset->toArray()['label_names']);
        }
    }
}
