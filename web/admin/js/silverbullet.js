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
    for ( var i = 0; i < this.datePickers.length;  i++) {
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
    resetButton.addEventListener('click', function() {
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
    this.element.style.color = 'grey';
    this.originalValue = element.value;
    this.originalBackgroundColor = element.style.backgroundColor;
    this.errorElement = document.createElement('span');
    this.errorElement.style.color = 'red';
    this.errorElement.style.fontWeight = 'bold';
    this.errorElement.style.display = 'inline';
    this.popupElement = new silverbullet.views.DatePickerCalendar(element.value);
    this.state = false;
    this.errorState = false;
    this.popupState = false;
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
 * 
 */
silverbullet.views.DatePicker.prototype.togglePopup = function(){
    if(this.popupState){
        this.element.parentNode.removeChild(this.popupElement.element);
        this.popupState = false;
    }else{
        this.element.parentNode.appendChild(this.popupElement.element);
        this.popupState = true;
    }
};

/**
 */
silverbullet.views.DatePicker.prototype.render = function(){
    var that = this;
    this.popupElement.render();
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
    this.element.addEventListener('click', function() {
        that.togglePopup();
    });

};

/**
 * Date picker popup object
 * 
 * @class
 * @constructor
 * @param {String} currentDate
 */
silverbullet.views.DatePickerCalendar = function(currentDate){
    silverbullet.views.ViewElement.call(this, document.createElement('div'));
    this.currentDate = currentDate;
    this.element.style.position = 'absolute';
    this.element.style.zIndex = '10';
    //this.element.style.width = '300px';
    //this.element.style.height = '200px';
    this.element.style.backgroundColor = 'white';
    this.element.style.border = '1px solid grey';
    this.element.style.display = 'block';
    this.element.style.padding = '10px';
    this.calendarPool = new silverbullet.views.DatePickerCalendarPool();
    this.state = false;
    
};
silverbullet.views.DatePickerCalendar.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.DatePickerCalendar.prototype.constructor = silverbullet.views.DatePickerCalendar;

/**
 * 
 */
silverbullet.views.DatePickerCalendar.prototype.render = function(){
    var calendarPanel = this.calendarPool.getCalendarPanel(this.currentDate);
    console.log('RENDERING '+this.currentDate);
    this.element.appendChild(calendarPanel);
};

silverbullet.views.DatePickerCalendarPool = function(){
    this.date = new Date();
    this.monthElements = [];
}

silverbullet.views.DatePickerCalendarPool.prototype.buildCalendarControls = function (title, calendarPanel) {
    console.log("BUIDLING CONTROLS "+title);
    var controlsPanel = document.createElement('div');
    controlsPanel.style.paddingBottom = '20px';
    var controlsTitle = document.createElement('p');
    controlsTitle.innerHTML = title;
    controlsTitle.style.display = 'inline';
    controlsTitle.style.paddingRight = '20px';

    controlsPanel.appendChild(controlsTitle);
    
    var previousButton = document.createElement('button')
    previousButton.setAttribute('type', 'button');
    previousButton.innerHTML = '<<';
    controlsPanel.appendChild(previousButton);
    
    var currentButton = document.createElement('button')
    currentButton.setAttribute('type', 'button');
    currentButton.innerHTML = '●';
    controlsPanel.appendChild(currentButton);
    
    var nextButton = document.createElement('button')
    nextButton.setAttribute('type', 'button');
    nextButton.innerHTML = '>>';
    controlsPanel.appendChild(nextButton);

    calendarPanel.appendChild(controlsPanel);
};

silverbullet.views.DatePickerCalendarPool.prototype.buildCalendarDays = function (year, month, calendarPanel) {
    console.log("BUIDLING DAYS "+year+" "+month);
    this.date.setFullYear(year);
    this.date.setMonth(month);
    this.date.setDate(1);
    
    var day = this.date.getDay();
    console.log("DAY "+day);
    if(day > 0){
        console.log("UPDATING MONTH "+(month - 1));
        //this.date.setMonth(month - 1);
        //console.log("month "+this.date.getMonth()+" month + 1 "+(month + 1));
        this.date.setDate(0);
        var lastDay = this.date.getDate();
        this.date.setDate(lastDay - day + 1);
    }

    console.log("month "+this.date.getMonth()+" month + 1 "+(month + 1));
    var daysElement = document.createElement('div');
    //var startMonth = this.date.getMonth();
    //var startDate = this.date.getDate();
    while(this.date.getMonth() < month + 1){
        var week = 0;
        for(var i = 0; i < 7; i++){
            var dateValue = this.date.getDate();
            var dateElement = document.createElement('div');
            dateElement.style.display = 'inline-table';
            dateElement.style.cursor = 'pointer';
            dateElement.style.textAlign = 'center';
            dateElement.style.width = '30px';
            dateElement.innerHTML = dateValue;
            calendarPanel.appendChild(dateElement);
            this.date.setDate(dateValue + 1);
        }
        calendarPanel.appendChild(document.createElement('br'));
    }
    calendarPanel.appendChild(daysElement)
};

silverbullet.views.DatePickerCalendarPool.prototype.addCalendarPanel = function (year, month, panel) {
    if(typeof this.monthElements[year] == 'undefined'){
        this.monthElements[year] = [];
    }
    this.monthElements[year][month] = panel;
};

/**
 * 
 */
silverbullet.views.DatePickerCalendarPool.prototype.getCalendarPanel = function (currentDate) {
    console.log("GET CALENDAR PANEL "+currentDate);
    var currentTimeStamp = Date.parse(currentDate);
    if(isNaN(currentTimeStamp)){
        currentTimeStamp = Date.now();
    }
    this.date.setTime(currentTimeStamp);
    var year = this.date.getFullYear();
    var month = this.date.getMonth();
    console.log("GET CALENDAR PANEL "+year+" "+month+" "+ this.date.getDate() );
    if (typeof this.monthElements[year] == 'undefined' || typeof this.monthElements[year][month] == 'undefined') {
        var calendarPanel = document.createElement('div');
            this.buildCalendarControls(year + ', ' + (month+1), calendarPanel);
            this.buildCalendarDays(year, month, calendarPanel);
        this.addCalendarPanel(year, month, calendarPanel);
    }
    console.log(this.monthElements[year][month]);
    return this.monthElements[year][month];
    
};

