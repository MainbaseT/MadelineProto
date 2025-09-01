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

namespace danog\MadelineProto\FileRefExtractor\Ops;

use danog\MadelineProto\FileRefExtractor\ActionOp;
use danog\MadelineProto\FileRefExtractor\TLContext;
use danog\MadelineProto\FileRefExtractor\TypedOp;
use Webmozart\Assert\Assert;

final readonly class CallOp implements ActionOp
{
    /** @param TypedOp[] $args */
    public function __construct(
        private readonly string $method,
        private readonly array $args
    ) {
        Assert::allIsInstanceOf($args, TypedOp::class);
    }
    public function normalize(array $stack, string $current, bool $ignoreFlag): ?ActionOp
    {
        $final = [];
        $isDifferent = false;
        foreach ($this->args as $from => $to) {
            $normalized = $to->normalize($stack, $current, $ignoreFlag);
            if ($normalized === null) {
                return null;
            }
            if ($normalized !== $to) {
                $isDifferent = true;
            }
            $final[$from] = $normalized;
        }
        if ($isDifferent) {
            return new self($this->method, $final);
        }
        return $this;
    }

    public static function simple(string $method, string $constructor, array $args): self
    {
        $final = [];
        foreach ($args as $from => $to) {
            if (!$to instanceof TypedOp) {
                $to = new CopyOp([[$constructor, $to]]);
            }
            $final[$from] = $to;
        }
        return new CallOp($method, $final);
    }

    public function build(TLContext $tl): void
    {
        $final = [];
        $tl->validateParams($this->method, false, $this->args);
        $types = [];
        foreach ($this->args as $from => $to) {
            $final[] = ['_' => 'typedOpArg', 'key' => $from, 'value' => $to->build($tl)];
            $types[$from] = $to->getType($tl);
        }

        $tl->buildMode->addNode($tl, [
            '_' => 'callOp',
            'method' => $this->method,
            'args' => $final,
        ]);
    }
}
