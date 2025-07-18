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

namespace danog\MadelineProto\FileRefExtractor;

use AssertionError;
use danog\MadelineProto\FileRefExtractor\Ops\ExtractFromHereOp;
use Webmozart\Assert\Assert;

final readonly class TLContext
{
    public function __construct(
        public TLWrapper $tl,
        public ?string $position = null,
        public bool $normalized = false,
    ) {
    }

    /**
     * @param Op[] $params
     */
    public function validateParams(string $constructor, bool $isCons, array $params): void
    {
        if ($isCons) {
            $data = $this->tl->tl->getConstructors()->findByPredicate($constructor);
        } else {
            $data = $this->tl->tl->getMethods()->findByMethod($constructor);
        }
        Assert::notFalse($data, "Constructor or method not found for $constructor");
        foreach ($data['params'] as $param) {
            if (!isset($params[$param['name']])) {
                if (isset($param['pow']) || $param['name'] === 'flags') {
                    continue;
                }
                throw new AssertionError("Mandatory parameter {$param['name']} not found in constructor or method $constructor");
            }
            if (isset($param['subtype'])) {
                $t = "Vector<{$param['subtype']}>";
            } else {
                $t = $param['type'];
            }
            $gotT = $params[$param['name']]->getType($this);
            if ($t !== $gotT) {
                throw new AssertionError("Parameter {$param['name']} in constructor or method $constructor has type $t but got $gotT");
            }
            unset($params[$param['name']]);
        }
        if ($params) {
            $extra = implode(', ', array_keys($params));
            throw new AssertionError("Extra parameters in constructor or method $constructor: $extra");
        }
    }

    public function getTypeAtPosition(FieldExtractorOp $_path): string
    {
        if ($_path instanceof ExtractFromHereOp) {
            Assert::eq($this->position, $_path->path[0], "getTypeAtPosition: Current constructor {$this->position} does not match expected constructor {$_path->path[0]}");
        }
        $path = $_path->path;
        $idx = 0;
        $type = null;
        $realType = null;
        $hadFlag = false;
        do {
            if ($realType !== null
                && !isset($this->tl->getConstructorsOfType($realType, true)[$path[$idx]])
                && !isset($this->tl->getMethodsOfType($realType, true)[$path[$idx]])
            ) {
                throw new AssertionError("{$path[$idx]} is NOT a constructor of type $type, path: " . json_encode($path));
            }
            $constructor = $this->tl->tl->getConstructors()->findByPredicate($path[$idx]);
            if ($constructor === false) {
                $constructor = $this->tl->tl->getMethods()->findByMethod($path[$idx]);
            }
            Assert::notFalse($constructor, "Constructor or method not found for path: " . json_encode($path));

            $idx++;
            $type = null;
            if ($path[$idx] === '') {
                Assert::true(isset($constructor['method']), "Expected method at position $idx in path: " . json_encode($path));
                $type = $constructor['type'];
                $realType = $constructor['subtype'] ?? $constructor['type'];
                continue;
            }
            $n = $constructor['predicate'] ?? $constructor['method'];
            foreach ($constructor['params'] as $param) {
                if ($param['name'] === $path[$idx]) {
                    $hadFlag = $hadFlag || isset($param['pow']);
                    $type = isset($param['subtype']) ? "Vector<{$param['subtype']}>" : $param['type'];
                    $realType = $param['subtype'] ?? $param['type'];
                    break;
                }
            }
            Assert::notNull($type, "Parameter {$path[$idx]} not found in constructor or method $n: " . json_encode($path));
            Assert::notNull($realType, "Parameter {$path[$idx]} not found in constructor or method $n: " . json_encode($path));
        } while (++$idx < \count($path));
        if ($_path->isFlag != $hadFlag && (!$this->normalized || !$hadFlag)) {
            $hadFlag = $hadFlag ? 'flag' : 'no flag';
            $expectedFlag = $_path->isFlag ? 'flag' : 'no flag';
            throw new AssertionError("Expected $expectedFlag; got $hadFlag at " . json_encode($path));
        }

        return $type;
    }
}
