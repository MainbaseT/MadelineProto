<?php

declare(strict_types=1);

/**
 * This file is part of MadelineProto.
 * MadelineProto is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * MadelineProto is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Affero General Public License for more details.
 * You should have received a copy of the GNU General Public License along with MadelineProto.
 * If not, see <http://www.gnu.org/licenses/>.
 *
 * @author    Daniil Gentili <daniil@daniil.it>
 * @copyright 2016-2025 Daniil Gentili <daniil@daniil.it>
 * @license   https://opensource.org/licenses/AGPL-3.0 AGPLv3
 * @link https://docs.madelineproto.xyz MadelineProto documentation
 */

namespace danog\MadelineProto\FileRefExtractor\BuildMode;

use AssertionError;
use danog\MadelineProto\FileRefExtractor\BuildMode;
use danog\MadelineProto\FileRefExtractor\TLContext;
use danog\MadelineProto\Magic;
use danog\MadelineProto\MTProto;
use danog\MadelineProto\Settings\TLSchema;
use danog\MadelineProto\TL\TL;
use ReflectionClass;
use Webmozart\Assert\Assert;

final class Ast implements BuildMode
{
    /** @var array<string, array{name: string, type: string}> */
    public array $stored = [];
    /** @var array<string, string> */
    public array $storedNames = [];
    public int $storedFlags = 0;

    public ?string $curKey = null;

    private array $output = [];
    private ?string $needsParent = null;

    public function __construct(
        public readonly bool $allowBackrefs,
        public readonly bool $allowUnpacking,
        private array $outputSchema = []
    ) {
    }

    public function getOutput(): string
    {
        $value = ['_' => 'fileReferenceOrigins', 'ctxs' => $this->output];
        Magic::start(false);

        $s = new TLSchema;
        $s = $s->setOther(['filerefs' => __DIR__ . '/../../../src/TL_filerefs.tl']);
        $TL = new TL((new ReflectionClass(MTProto::class))->newInstanceWithoutConstructor());
        $TL->init($s);
        $serialized = $TL->serializeObject(['type' => 'FileReferenceOrigins'], $value, '');
        //$valueDe = $TL->deserialize($serialized, ['type' => '', 'connection' => null, 'encrypted' => true]);
        return $serialized;
    }

    private static function stringifySchema(string $constructor, array $params): string
    {
        $paramsStr = "$constructor ";
        foreach ($params as $name => $type) {
            $paramsStr .= "$name:$type ";
        }
        $paramsStr .= '= FileSource;';
        return $paramsStr;
    }
    public function addNode(TLContext $ctx, ?array $action = null, ?string $why = null): void
    {
        $out = [
            '_' => 'origin',
            'predicate' => $ctx->position,
            'is_constructor' => $ctx->isConstructor,
        ];
        if ($this->needsParent !== null) {
            $out['needs_parent'] = $this->needsParent;
            $out['parent_is_constructor'] = $ctx->tl->isConstructor($this->needsParent);
        }
        if ($action !== null) {
            $out['action'] = $action;

            Assert::keyExists($action, 'stored_constructor');

            $constructor = $action['stored_constructor'];

            $names = $this->storedNames;
            $flags = [];
            if ($this->storedFlags) {
                foreach ($names as $name => $type) {
                    if (str_starts_with($type, 'flags.')) {
                        $flags[$name] = $type;
                    }
                }
                $names = [
                    'flags' => '#',
                    ...$names
                ];
            }

            if (isset($this->outputSchema[$constructor])) {
                $existing = $this->outputSchema[$constructor];
                foreach ($existing as $name => $type) {
                    if (str_starts_with($type, 'flags.')) {
                        if (isset($flags[$name])) {
                            unset($flags[$name], $names[$name]);
                        }
                    }
                }
                if ($flags) {
                    throw new AssertionError("Have leftover flags: ".implode(' ', $flags));
                }
                foreach ($existing as $name => $type) {
                    if (isset($names[$name])) {
                        if ($names[$name] === $type) {
                            unset($names[$name]);
                        } else {
                            throw new AssertionError("Type mismatch for $constructor.$name: have {$names[$name]}, need $type");
                        }
                    } elseif (!str_starts_with($type, 'flags.') && $name !== 'flags') {
                        throw new AssertionError("Missing pre-existing parameter $constructor.$name for $constructor");
                    }
                }
                foreach ($names as $name => $type) {
                    throw new AssertionError("Leftover parameter $constructor.$name:$type for ".self::stringifySchema($constructor, $existing));
                }
            } else {
                $this->outputSchema[$constructor] = $names;
            }


            $this->storedFlags = 0;
            $this->stored = [];
            $this->storedNames = [];
            Assert::null($why);
        } elseif ($why !== null) {
            $out['action'] = ['_' => 'noOp', 'why' => $why];
            Assert::null($action);
            Assert::isEmpty($this->stored);
            Assert::isEmpty($this->storedNames);
            Assert::eq($this->storedFlags, 0);
        } else {
            throw new AssertionError("Either 'action' or 'why' must be provided.");
        }
        $this->output[] = $out;
        $this->needsParent = null;
        $this->curKey = null;
    }

    public function getNeedsParent(): ?string
    {
        return $this->needsParent;
    }

    public function setNeedsParent(string $needsParent): void
    {
        if (!$this->allowBackrefs) {
            throw new \LogicException('Cannot set needsParent when backreferences are not allowed.');
        }
        if ($this->needsParent !== null && $this->needsParent !== $needsParent) {
            throw new \LogicException("Cannot change needsParent from {$this->needsParent} to {$needsParent} once it has been set.");
        }
        $this->needsParent = $needsParent;
    }
}
