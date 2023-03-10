# Laravel Data
My Customized Data layers for Laravel


## About
Data layer is primarily responsible for CRUD processing to database.  
This package simplifies implementation of a customized data layer for Laravel.


## Details
### Model
Model implements functions to safely "CREATE", "UPDATE", and "DELETE" to database using transactions.  
Thus, you will not have to worry about integrity of your database in event that CRUD process is interrupted.  
You can also refer to logging_transaction in config/mycustom.php to log about transaction processing.

### Repository
Repository wraps Eloquent and implements functions to comfortably "Read" database.  
Also, by using Result as Repository's data transfer object, there is no need to worry about passing unnecessary data contained in existing entity Model to View.

### Result
Result is data transfer object used in Repository.  
Result can be used as a simple data transfer object with only database records by transferring only necessary data from Model.  
You can also refer to result_nullable in config/mycustom.php to determine if null property is included during jsonSerialize.


## Installation
You can install package via composer:
```
composer require takeru-yamamoto/laravel-data
```


## Usage
### Extension of Model
1. Declares use of BaseModel trait in any models.
```
use MyCustom\Models\BaseModel;
```

2. Declares again use of BaseModel trait within any model.
```
use BaseModel;
```

### Implement Repository layer
1. Create arbitrary repository and result.
2. Define properties necessary for Result, and describe process of transferring them from Model with __construct.
3. Declares use of BaseRepository in created repository.
```
use MyCustom\Repositories\BaseRepository;
```
4. Extend created repository with BaseRepository.
5. Implement following functions in created repository.
    * model()   : Returns Model to be used in Repository.
    * toResult(): Pass Model to Result.
```
protected function model()
```
```
public function toResult(object $entity)
```