<?php

namespace tests\fixtures;

use yii\test\ActiveFixture;

class TreeSpeciesFixture extends ActiveFixture
{
    public $modelClass = 'app\models\TreeSpecies';
    public $dataFile = '@tests/fixtures/data/tree_species.php';

}
