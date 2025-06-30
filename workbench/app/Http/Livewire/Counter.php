<?php

namespace App\Http\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('fixtures::app')]
class Counter extends Component
{
    public $count = 1;

    public function increment()
    {
        $this->count++;
    }

    public function decrement()
    {
        $this->count--;
    }

    public function render()
    {
        return <<<'HTML'
        <div>{{ $count }}</div>
        HTML;
    }
}
