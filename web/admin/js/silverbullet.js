/**
 * Silverbullet pages library
 * @author Zilvinas Vaira
 */

/**
 * Silverbullet namespace
 * @namespace
 */

var silverbullet = {};

/**
 * Instantiates all views for Silverbullet application
 * @class Main Silverbullet application class
 * @constructor
 */
silverbullet.SilverbulletApplication = function (){
    /**
     * DatePicker []
     */
    this.datePickers = [];
    
};
/**
 * 
 * @param {SilverBullet.Views.DatePicker} datePicker
 */
silverbullet.SilverbulletApplication.prototype.addDatePicker = function (datePicker){
    this.datePickers [this.datePickers.length] = datePicker;
};

/**
 * 
 */
silverbullet.SilverbulletApplication.prototype.resetDates = function (){
    for ( var i in this.datePickers) {
        this.datePickers[i].addDefaultValue();
    }
};

/**
 * 
 */
silverbullet.SilverbulletApplication.prototype.start = function(){
    
    //Create and render DatePicker elements
    var datePickerElements = document.getElementsByClassName(silverbullet.views.DatePicker.ELEMENT_CLASS);
    for ( var i = 0; i < datePickerElements.length; i++) {
        var datePicker = new silverbullet.views.DatePicker(datePickerElements[i]);
        datePicker.render();
        this.addDatePicker(datePicker);
    }
    var resetButton = document.getElementById('sb-reset-dates');
    var that = this;
    resetButton.addEventListener('click', function(e) {
        that.resetDates(); 
    });
};

/**
 * Silverbullet Views namespace
 * @namespace
 */
silverbullet.views = {};


/**
 * Provides rendering and initialization interfaces
 * 
 * @class Defines abstract Silverbullet view class
 * @constructor
 * @param {Node} element
 */
silverbullet.views.ViewElement = function(element){
    /**
     * @param {HTMLElement} element
     */
    this.element = element;
};
/**
 * 
 */
silverbullet.views.ViewElement.prototype.render = function (){};

/**
 * Handles form elements for editing page
 * @class
 * @constructor
 * @param {HTMLElement} element
 */
silverbullet.views.DatePicker = function(element){
    silverbullet.views.ViewElement.call(this, element);
    this.originalValue = element.value;
    this.element.style.color = 'grey';
    this.originalBackgroundColor = element.style.backgroundColor;
    this.errorElement = document.createElement('span');
    this.errorElement.style.color = 'red';
    this.errorElement.style.fontWeight = 'bold';
    this.errorElement.style.display = 'inline';
    this.state = false;
    this.errorState = false;
};
silverbullet.views.DatePicker.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.DatePicker.prototype.constructor = silverbullet.views.DatePicker;
silverbullet.views.DatePicker.ELEMENT_CLASS = 'sb-date-picker';

/**
 * 
 * @param {String} token
 */
silverbullet.views.DatePicker.prototype.validateDate = function(token){
    var date = new Date(token);
    if(isNaN(date.getDate())){
        this.state = false;
        this.element.style.backgroundColor = 'pink';
        this.errorElement.innerHTML = "Must be a date!";
        this.element.parentNode.appendChild(this.errorElement);
        this.errorState = true;
    }else{
        if(!this.state){
            this.state = true;
            this.element.style.backgroundColor = this.originalBackgroundColor;
            if(this.errorState){
                this.element.parentNode.removeChild(this.errorElement);
                this.errorState = false;
            }
        }
    }
};

/**
 * 
 * @param {String} token
 */
silverbullet.views.DatePicker.prototype.filterDate = function(token){
    var date = new Date(token);
    if(!isNaN(date.getDay())){
        if(token.length == 4 || token.length == 7){
            this.element.value += '-';
        }
    }
};

/**
 * 
 */
silverbullet.views.DatePicker.prototype.removeDefaultValue = function(){
    if(this.element.value == this.originalValue){
        this.element.value = '';
        this.element.style.color = 'black';
    }
};

/**
 * 
 */
silverbullet.views.DatePicker.prototype.addDefaultValue = function(){
    this.element.value = this.originalValue;
    this.element.style.color = 'grey';
};

/**
 * 
 * @returns {Boolean}
 */
silverbullet.views.DatePicker.prototype.isValid = function(){
    return this.state;
};

/**
 */
silverbullet.views.DatePicker.prototype.render = function(){
    var that = this;
    this.element.addEventListener('focus', function() {
        that.removeDefaultValue();
    });
    this.element.addEventListener('blur', function() {
        if(that.element.value == that.originalValue || that.element.value == ''){
            that.addDefaultValue();
        }
        that.validateDate(that.element.value);
    });
    this.element.addEventListener('keyup', function() {
        //that.validateDate(that.element.value);
        //that.filterDate(that.element.value);
    });

};

