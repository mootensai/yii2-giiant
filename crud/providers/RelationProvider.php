<?php
/**
 * Created by PhpStorm.
 * User: tobias
 * Date: 14.03.14
 * Time: 10:21
 */

namespace schmunk42\giiant\crud\providers;

use yii\helpers\Inflector;

class RelationProvider extends \schmunk42\giiant\base\Provider
{
    public function activeField($column)
    {
        #$column   = $this->generator->getTableSchema()->columns[$attribute];
        $relation = $this->generator->getRelationByColumn($this->generator->modelClass, $column);
        if ($relation) {
            switch (true) {
                case (!$relation->multiple):
                    // $name = $this->generator->getNameAttribute(get_class($relation->primaryModel));
                    $pk   = key($relation->link); // TODO - fix detection, see generateAttribute...
                    $name = $this->generator->getModelNameAttribute($relation->modelClass);
                    $code = <<<EOS
\$form->field(\$model, '{$column->name}')->dropDownList(
    \yii\helpers\ArrayHelper::map({$relation->modelClass}::find()->all(),'{$pk}','{$name}'),
    ['prompt'=>'Choose...']    // active field
);
EOS;
                    return $code;
                default:
                    return null;

            }
        }
    }

    public function attributeFormat($column)
    {
        $relation = $this->generator->getRelationByColumn($this->generator->modelClass, $column);
        if ($relation) {
            if ($relation->multiple) {
                return null;
            }
            $title          = $this->generator->getModelNameAttribute($relation->modelClass);
            $route          = $this->generator->createRelationRoute($relation, 'view');
            $relationGetter = 'get' . Inflector::id2camel(
                    str_replace('_id', '', $column->name),
                    '_'
                ) . '()'; // TODO: improve detection
            $code           = <<<EOS
[
    'format'=>'html',
    'attribute'=>'$column->name',
    'value' => Html::a(\$model->{$relationGetter}->one()?\$model->{$relationGetter}->one()->{$title}:'', ['{$route}', 'id' => \$model->{$column->name}]),
]
EOS;
            return $code;
        }
    }

    public function columnFormat($column, $model)
    {
        $relation = $this->generator->getRelationByColumn($model, $column);
        if ($relation) {
            if ($relation->multiple) {
                return null;
            }
            $title          = $this->generator->getModelNameAttribute($relation->modelClass);
            $route          = $this->generator->createRelationRoute($relation, 'view');
            $relationGetter = 'get' . Inflector::id2camel(
                    str_replace('_id', '', $column->name),
                    '_'
                ) . '()'; // TODO: improve detection

            // TODO: improve closure style, implement filter
            /* "filter" => yii\helpers\ArrayHelper::map(common\models\starrag\Spectrum::find()->all(),'id','default_title') */
            $pk   = key($relation->link);
            $code = <<<EOS
[
            "class" => yii\\grid\\DataColumn::className(),
            "attribute" => "{$column->name}",
            "value" => function(\$model){
                if (\$rel = \$model->{$relationGetter}->one()) {
                    return yii\helpers\Html::a(\$rel->{$title},["{$route}","id" => \$rel->{$pk}]);
                } else {
                    return '';
                }
            },
            "format" => "raw",
]
EOS;
            return $code;
        }
    }


    // TODO: params is an array, because we need the name, improve params
    public function relationGrid($data)
    {
        $name           = $data[1];
        $relation       = $data[0];
        $showAllRecords = isset($data[2]) ? $data[2] : false;
        $model          = new $relation->modelClass;
        $counter        = 0;
        $columns        = '';
        foreach ($model->attributes AS $attr => $value) {
            if ($counter > 8) {
                continue;
            }
            if (!isset($model->tableSchema->columns[$attr])) {
                continue; // virtual attributes
            }

            $code = $this->generator->columnFormat($model->tableSchema->columns[$attr], $model);
            if ($code == false) {
                continue;
            }
            $columns .= $code . ",\n";
            $counter++;
        }


        // TODO: implement extended action column with attach and detach buttons
        $reflection   = new \ReflectionClass($relation->modelClass);
        $actionColumn = [
            'class'      => 'yii\grid\ActionColumn',
            'template'   => $showAllRecords ? '{view} {update}' : '{delete}',
            'controller' => $this->generator->pathPrefix . Inflector::camel2id($reflection->getShortName(), '-', true)
        ];
        $columns .= var_export($actionColumn, true) . ",";

        $query = $showAllRecords ?
            "'query' => \\{$relation->modelClass}::find()" :
            "'query' => \$model->get{$name}()";
        $code  = '';
        $code .= <<<EOS
\\yii\\grid\\GridView::widget([
    'dataProvider' => new \\yii\\data\\ActiveDataProvider([{$query}, 'pagination' => ['pageSize' => 10]]),
    'columns' => [$columns]
]);
EOS;
        return $code;
    }


}