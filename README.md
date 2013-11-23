yii-versionedactiverecord
=========================

The VersionedActiveRecord Yii model class adds up functionality of saving each new version of a model into the database. All updates are prevented, new row inserting ater each save. Last row with IsActual field equal to 1 is current version of a model.
All deletes are prevented too. All deleting rows became inactual with setted deletedDate field.

Primary key is no more unique identifier of your model, use KeyColumn for identifying. If you need 2 columns for identifying use KeyColumn + SubKeyColumn.

Requirements 
=========================

Yii 1.1 or above

Installation
========================= 

To use this extension:

1) copy VersionedActiveRecord.php to your models directory

2) extend your versioned model

	class Contract extends VersionedActiveRecord
	...

Usage
=========================

You can save new records

	$model = new Contract;
	$model->attributes = $_POST['Contract'];
	$model->save();
	
After successful validation there will be new row in the database with specidied KeyColumn (and Subkey if needed). KeyColumn may be contract number for example. KeyColumn automatically setted to the first id of the version. After all last update it won't change. You can find you actual contract just by

	Contract::model()->findByAttributes(array('ParentID' => <your_first_id_here>)); // ParentID is the name of your KeyColumn here.

Or you can update last actual model

	$model = Contract::model()->findByPk(10);
	$model->attributes = $_POST['Contract'];	
	$model->save();
	
Here you trying to find Actual version with PK 10, change some attributes and insert new version row in the database. 
All old rows with same KeyColumn (and SubkeyColumn if persists) will be updated to inactual version (IsActual field will be setted to 0), $model became a new row with next autoincremented primary key.

Or you can delete the model

	$model->delete();
	
Inside this call will be executed several updates. 
All rows with same KeyColumn (and Subkey if persists) including deleted one will be updated to inactual version (IsActual field will be setted to 0) and then current row will set up own deletedDate field to current date.

You also can find inactual versions after reseting scope. For example you can find old row with Primary key $pk;

	$model = Contract::model()->resetScope()->findByPk($pk);
	
Default scope in VersionActiveRecord always sets ActualColumn to 1.
