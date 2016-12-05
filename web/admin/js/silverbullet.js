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
 * 
 * @constructor Main Silverbullet application
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
 * @constructor Defines abstract Silverbullet view class
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
 * Wraps text input element with date picker features
 * 
 * @constructor Date picker input element
 * @param {HTMLElement} element Text input element
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
    this.popupElement = new silverbullet.views.CalendarPanel(element.value, this);
    this.state = false;
    this.errorState = false;
    this.popupState = false;
    this.button = this.element.parentNode.getElementsByTagName('button')[0];
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
 * @param {String} fullDateValue
 */
silverbullet.views.DatePicker.prototype.setDateValue = function(fullDateValue){
    this.element.value = fullDateValue;
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
 * 
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
    
    console.dir(this.button);
    this.button.addEventListener('click', function() {
        that.togglePopup();
    });
    this.button.addEventListener('blur', function() {
        if(that.popupElement.isMouseOut()){
            that.togglePopup();
        }
    });

};

/**
 * Provides a panel for date picker object with month and date controls
 * 
 * @constructor Popup calendar panel for Date picker
 * @param {String} currentDate
 */
silverbullet.views.CalendarPanel = function(currentDate, datePicker){
    silverbullet.views.ViewElement.call(this, document.createElement('div'));
    this.currentDate = currentDate;
    this.datePicker = datePicker;
    this.element.style.position = 'absolute';
    this.element.style.zIndex = '10';
    //this.element.style.width = '300px';
    //this.element.style.height = '200px';
    this.element.style.backgroundColor = 'white';
    this.element.style.border = '1px solid grey';
    this.element.style.display = 'block';
    this.element.style.padding = '10px';
    this.calendarPool = new silverbullet.views.CalendarPool(datePicker);
    this.mouseOverState = false;
    
};
silverbullet.views.CalendarPanel.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.CalendarPanel.prototype.constructor = silverbullet.views.CalendarPanel;

/**
 * 
 * @returns {Boolean}
 */
silverbullet.views.CalendarPanel.prototype.isMouseOut = function(){
    return !this.mouseOverState;
};

/**
 * 
 */
silverbullet.views.CalendarPanel.prototype.render = function(){
    var that = this;
    var calendarPanel = this.calendarPool.getCalendarPanel(this.currentDate);
    console.log('RENDERING '+this.currentDate);
    this.element.appendChild(calendarPanel);
    this.element.addEventListener('mouseover', function() {
        that.mouseOverState = true;
    });
    this.element.addEventListener('mouseout', function() {
        that.mouseOverState = false;
    });

};

/**
 * Provides single date element for calendar panel
 * 
 * @constructor Creates and references single date element
 */
silverbullet.views.CalendarDate = function () {
    silverbullet.views.ViewElement.call(this, document.createElement('div'));
};
silverbullet.views.CalendarDate.prototype = Object.create(silverbullet.views.ViewElement.prototype);
silverbullet.views.CalendarDate.prototype.constructor = silverbullet.views.CalendarDate;

/**
 * Pool object that stores unique Calendar panels
 * 
 * @constructor 
 */
silverbullet.views.CalendarPool = function(datePicker){
    this.date = new Date();
    this.currentDate = new Date();
    this.selectedDate = new Date();
    this.monthElements = [];
    this.weekDays = ["Sun", "Mon", "Tue","Wed", "Thu","Fri","Sat"];
    this.datePicker = datePicker;
}

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.buildCalendarControls = function (title, calendarPanel) {
    console.log("BUIDLING CONTROLS "+title);
    var controlsPanel = document.createElement('div');
    controlsPanel.style.paddingBottom = '20px';
    controlsPanel.style.textAlign = 'right';
    var controlsTitle = document.createElement('p');
    controlsTitle.innerHTML = title;
    controlsTitle.style.display = 'inline';
    controlsTitle.style.paddingRight = '40px';

    controlsPanel.appendChild(controlsTitle);
    
    var previousButton = document.createElement('button')
    previousButton.setAttribute('type', 'button');
    previousButton.innerHTML = '<';
    controlsPanel.appendChild(previousButton);
    
    var currentButton = document.createElement('button')
    currentButton.setAttribute('type', 'button');
    currentButton.innerHTML = '●';
    controlsPanel.appendChild(currentButton);
    
    var nextButton = document.createElement('button')
    nextButton.setAttribute('type', 'button');
    nextButton.innerHTML = '>';
    controlsPanel.appendChild(nextButton);

    calendarPanel.appendChild(controlsPanel);
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.buildCalendarDays = function (year, month, calendarPanel) {
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
    for(var i = 0; i < 7; i++){
        var dateElement = document.createElement('div');
        dateElement.style.display = 'inline-table';
        dateElement.style.textAlign = 'center';
        dateElement.style.width = '30px';
        dateElement.style.backgroundColor = '#F0F0F0';
        dateElement.style.borderBottom = '1px solid grey';
        dateElement.innerHTML = this.weekDays[i];
        calendarPanel.appendChild(dateElement);
    }
    calendarPanel.appendChild(document.createElement('br'));
    
    while(this.date.getMonth() < month + 1){
        var week = 0;
        for(var i = 0; i < 7; i++){
            var dateValue = this.date.getDate();
            var dateElement = document.createElement('div');
            dateElement.style.display = 'inline-table';
            dateElement.style.cursor = 'pointer';
            dateElement.style.textAlign = 'center';
            dateElement.style.width = '30px';
            dateElement.style.borderBottom = '1px solid grey';
            if(this.currentDate.toDateString() == this.date.toDateString()){
                dateElement.style.fontWeight = 'bold';
            }
            if(this.selectedDate.toDateString() == this.date.toDateString()){
                dateElement.style.backgroundColor = '#BCD7E8';
            }
            dateElement.innerHTML = dateValue;
            var originalColor;
            dateElement.addEventListener('mouseover', function () {
                originalColor = this.style.backgroundColor;
                this.style.backgroundColor = 'lightgrey';
	    });
            dateElement.addEventListener('mouseout', function () {
                this.style.backgroundColor = originalColor;
            });
            var that = this;
            var fullDateValue = this.date.toDateString();
            dateElement.addEventListener('click', function () {
                this.style.backgroundColor = originalColor;
                that.datePicker.setDateValue(fullDateValue);
                that.datePicker.togglePopup();
            });

            calendarPanel.appendChild(dateElement);
            this.date.setDate(dateValue + 1);
        }
        calendarPanel.appendChild(document.createElement('br'));
    }
    calendarPanel.appendChild(daysElement)
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.addCalendarPanel = function (year, month, panel) {
    if(typeof this.monthElements[year] == 'undefined'){
        this.monthElements[year] = [];
    }
    this.monthElements[year][month] = panel;
};

/**
 * 
 */
silverbullet.views.CalendarPool.prototype.getCalendarPanel = function (currentDate) {
    console.log("GET CALENDAR PANEL "+currentDate);
    var currentTimeStamp = Date.parse(currentDate);
    this.selectedDate.setTime(currentTimeStamp);
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

