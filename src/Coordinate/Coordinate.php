<?php namespace DCarbone\PHPConsulAPI\Coordinate;

/*
   Copyright 2016-2017 Daniel Carbone (daniel.p.carbone@gmail.com)

   Licensed under the Apache License, Version 2.0 (the "License");
   you may not use this file except in compliance with the License.
   You may obtain a copy of the License at

       http://www.apache.org/licenses/LICENSE-2.0

   Unless required by applicable law or agreed to in writing, software
   distributed under the License is distributed on an "AS IS" BASIS,
   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
   See the License for the specific language governing permissions and
   limitations under the License.
*/

use DCarbone\Go\Time;
use DCarbone\PHPConsulAPI\AbstractModel;

/**
 * Class Coordinate
 * @package DCarbone\PHPConsulAPI\Coordinate
 */
class Coordinate extends AbstractModel {
    /** @var int[] */
    public $Vec = [];
    /** @var float */
    public $Error = 0.0;
    /** @var float */
    public $Adjustment = 0.0;
    /** @var float */
    public $Height = 0.0;

    /**
     * Coordinate constructor.
     * @param array|\DCarbone\PHPConsulAPI\Coordinate\CoordinateConfig $data
     */
    public function __construct($data = []) {
        if (is_array($data)) {
            parent::__construct($data);
        } else if ($data instanceof CoordinateConfig) {
            $this->Vec = array_fill(0, $data->Dimensionality, 0.0);
            $this->Error = $data->VivaldiErrorMax;
            $this->Adjustment = 0.0;
            $this->Height = $data->HeightMin;
        } else {
            throw new \InvalidArgumentException(sprintf(
                '%s::__construct - Argument 1 must be array of values or instance of %s, %s seen',
                get_class($this),
                CoordinateConfig::class,
                is_object($data) ? get_class($data) : gettype($data)
            ));
        }
    }

    /**
     * @return int[]
     */
    public function getVec(): array {
        return $this->Vec;
    }

    /**
     * @return float
     */
    public function getError(): float {
        return $this->Error;
    }

    /**
     * @return float
     */
    public function getAdjustment(): float {
        return $this->Adjustment;
    }

    /**
     * @return float
     */
    public function getHeight(): float {
        return $this->Height;
    }

    /**
     * @return bool
     */
    public function isValid(): bool {
        foreach ($this->Vec as $vec) {
            if (!is_finite($vec)) {
                return false;
            }
        }
        return is_finite($this->Error) && is_finite($this->Adjustment) && is_finite($this->Height);
    }

    /**
     * @param \DCarbone\PHPConsulAPI\Coordinate\Coordinate $other
     * @return bool
     */
    public function isCompatibleWith(Coordinate $other): bool {
        return count($this->Vec) === count($other->Vec);
    }

    /**
     * @param \DCarbone\PHPConsulAPI\Coordinate\CoordinateConfig $config
     * @param float $force
     * @param \DCarbone\PHPConsulAPI\Coordinate\Coordinate $other
     * @return \DCarbone\PHPConsulAPI\Coordinate\Coordinate
     */
    public function applyForce(CoordinateConfig $config, float $force, Coordinate $other): Coordinate {
        if (!$this->isCompatibleWith($other)) {
            throw new DimensionalityConflictException();
        }

        $ret = clone $this;
        list($unit, $mag) = unitVectorAt($this->Vec, $other->Vec);
        $ret->Vec = add($ret->Vec, mul($unit, $force));
        if ($mag > ZeroThreshold) {
            $ret->Height = max(($ret->Height + $other->Height) * $force / $mag + $ret->Height, $config->HeightMin);
        }

        return $ret;
    }

    /**
     * @param \DCarbone\PHPConsulAPI\Coordinate\Coordinate $other
     * @return \DCarbone\Go\Time\Duration
     */
    public function distanceTo(Coordinate $other): Time\Duration {
        static $secondsToNanoseconds = 1.0e9;

        if (!$this->isCompatibleWith($other)) {
            throw new DimensionalityConflictException();
        }

        $dist = $this->rawDistanceTo($other);
        $adjustedDist = $dist + $this->Adjustment + $other->Adjustment;
        if ($adjustedDist > 0.0) {
            $dist = $adjustedDist;
        }
        return new Time\Duration($dist * $secondsToNanoseconds);
    }

    /**
     * @param \DCarbone\PHPConsulAPI\Coordinate\Coordinate $other
     * @return float
     */
    protected function rawDistanceTo(Coordinate $other): float {
        return magnitude(diff($this->Vec, $other->Vec)) + $this->Height + $other->Height;
    }
}