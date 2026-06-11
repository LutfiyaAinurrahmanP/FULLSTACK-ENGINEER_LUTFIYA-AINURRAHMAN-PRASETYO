<?php

/**
 * Task 2: Hidden Item Game
 * 
 * This script finds all probable locations for a hidden item in a grid,
 * given that the player must start at 'X', move North A steps, then
 * East B steps, then South C steps (where A, B, C > 0), staying on
 * clear paths ('.').
 */

class HiddenItemGame
{
    private $grid;
    private $startX = -1;
    private $startY = -1;

    public function __construct(array $grid)
    {
        $this->grid = $grid;
        $this->findStart();
    }

    /**
     * Locate the 'X' starting position in the grid.
     */
    private function findStart()
    {
        foreach ($this->grid as $y => $row) {
            $x = strpos($row, 'X');
            if ($x !== false) {
                $this->startX = $x;
                $this->startY = $y;
                return;
            }
        }
        throw new Exception("Starting position 'X' not found in the grid.");
    }

    /**
     * Check if a given coordinate is a clear path or valid position.
     */
    private function isClear($x, $y)
    {
        if (!isset($this->grid[$y])) return false;
        if ($x < 0 || $x >= strlen($this->grid[$y])) return false;

        $char = $this->grid[$y][$x];
        // The path is clear if it's '.', the start 'X', or our destination marker '$'
        return $char === '.' || $char === 'X' || $char === '$';
    }

    /**
     * Simulate movement constraints to find all probable locations.
     * Moves: North A steps -> East B steps -> South C steps (A,B,C > 0)
     */
    public function findProbableLocations()
    {
        $locations = [];

        // 1. Up/North A steps
        for ($a = 1;; $a++) {
            $ny = $this->startY - $a;
            $nx = $this->startX;
            if (!$this->isClear($nx, $ny)) break; // Hit obstacle or boundary

            // 2. Right/East B steps
            for ($b = 1;; $b++) {
                $ex = $nx + $b;
                $ey = $ny;
                if (!$this->isClear($ex, $ey)) break;

                // 3. Down/South C steps
                for ($c = 1;; $c++) {
                    $sx = $ex;
                    $sy = $ey + $c;
                    if (!$this->isClear($sx, $sy)) break;

                    // Found a valid destination satisfying all conditions
                    $locations[] = [$sx, $sy];
                }
            }
        }

        // Filter unique locations
        $uniqueLocations = [];
        foreach ($locations as $loc) {
            $key = $loc[0] . ',' . $loc[1];
            $uniqueLocations[$key] = $loc;
        }

        return array_values($uniqueLocations);
    }

    /**
     * Print the results including the coordinate list and marked grid.
     */
    public function printGridWithLocations(array $locations)
    {
        $displayGrid = $this->grid;

        // Mark locations with '$'
        foreach ($locations as $loc) {
            $x = $loc[0];
            $y = $loc[1];
            $displayGrid[$y][$x] = '$';
        }

        echo "Probable Item Locations (x, y) where (0,0) is top-left:\n";
        foreach ($locations as $loc) {
            echo "- (" . $loc[0] . ", " . $loc[1] . ")\n";
        }

        echo "\nGrid with probable locations marked as '$':\n";
        foreach ($displayGrid as $row) {
            echo $row . "\n";
        }
    }
}

// Define the grid exactly as portrayed, adjusting Row 1 to be a proper 8-character string 
// to match the rectangular nature of the provided maze layout.
$gridMap = [
    "########",
    "#......#",
    "#.###..#",
    "#...#.##",
    "#X#....#",
    "########"
];

try {
    $game = new HiddenItemGame($gridMap);
    $probableLocations = $game->findProbableLocations();
    $game->printGridWithLocations($probableLocations);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
