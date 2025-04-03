<?php

declare(strict_types=1);

namespace App;

class Elephant
{
    private string $name;
    private array $availableNames;
    private string $image = <<<ELEPHANT
                        ____
                   .---'-    \
      .-----------/           \
     /           (         ^  |   __
&   (             \        O  /  / .'
'._/(              '-'  (.   (_.' /
     \                    \     ./
      |    |       |    |/ '._.'
       )   @).____\|  @ |
   .  /    /       (    | mrf
  \|, '_:::\  . ..  '_:::\ ..\).
ELEPHANT;

    private string $imageFlipped = <<<ELEPHANT
         ____
       /    _'___.
      /           \-----------.
 __   |                        \
'. \  \  O        /             )   &
  \ '._)   .)  '-              )\_.'
    \.     /                    /
      '._.'\ |    |       |    |
           | @  |/____.(@ |    )
      mrf | )    (       \    \  .
      .(/..\/ :::_'. ..  . /:::_' ,|/
ELEPHANT;

    public function __construct(string $usersName)
    {
        $this->availableNames = array_filter($this->listPossibleNames(), fn ($name) => strtolower($name) !== strtolower($usersName));
        $this->name = $this->pickRandomAvailableName();
    }

    public function takePicture(): string
    {
        return $this->image;
    }

    public function takePictureFlipped(): string
    {
        return $this->imageFlipped;
    }

    public function listAvailableNames(array $guessedNames = []): array
    {
        return array_filter($this->availableNames, function ($name) use ($guessedNames) {
            return !in_array($name, $guessedNames);
        });
    }

    public function searchAvailableNames(string $value, array $guessedNames): array
    {
        return array_filter($this->listAvailableNames($guessedNames), function ($name) use ($value) {
            return str_contains(strtolower($name), strtolower($value));
        });
    }

    public function guessName(string $name): bool
    {
        return $name === $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    private function listPossibleNames(): array
    {
        return [
            'Aisha',
            'Alberto',
            'Ashley',
            'Chen',
            'Diego',
            'Freya',
            'Jamal',
            'Josh',
            'Kat',
            'Krishna',
            'Luna',
            'Marcus',
            'Nadia',
            'Nik',
            'Omar',
            'Pakinam',
            'Saki',
            'Sam',
            'Taika',
            'Zara',
        ];
    }

    private function pickRandomAvailableName(): string
    {
        return $this->availableNames[array_rand($this->availableNames)];
    }
}
