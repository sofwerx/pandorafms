# -*- coding: utf-8 -*-
from selenium import selenium
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from common_functions_60 import *

import random, time
import string


def create_planned_downtime(driver,name,group,type_planned,execution,description=None,date_from=None,date_to=None,hour_from=None,hour_to=None,periodicity_type=None,from_day=None,to_day=None,list_days=None,agent_module_list=None):

	
	#type_planned is: Disabled Agents, Quiet or Disabled only Alerts
	
	#If execution = Once, date_from, date_to, hour_from and hour_to is required. Example time_from: 2016/07/05 hour_from 15:46:48
	#If exexution = Periodically, hour_from, hour_to is required, periodicity_type is weekly or monthly
	
	#If periodicity_type is Monthly insert from_day and to_day in argument function
	#If periodicity_type is Weekly insert list_days, Example list_days=("monday","saturday","sunday")
	
	#Execution by default is Once, date_from and date_to is date it's today's date

	#agent_module_list is a list of tuples: lista = [('agent_name_1',['module_name_1','module_name_2']),('agent_name_2',[])]
	#CAREFUL! [] means 'Any' module

	#Self checks
	if (date_from is None and date_to is not None) or (date_from is not None and date_to is None):
		raise Exception("If one date is provided, the other should be provided too")

	click_menu_element(driver,"Scheduled downtime")
	driver.find_element_by_id("submit-create").click()
	
	driver.find_element_by_id("text-name").clear()
	driver.find_element_by_id("text-name").send_keys(name)	
	
	driver.find_element_by_xpath('//option[contains(.,"'+group+'")]').click()
	
	if description != None:
		driver.find_element_by_id("textarea_description").clear()
		driver.find_element_by_id("textarea_description").send_keys(description)
			
	driver.find_element_by_xpath('//option[contains(.,"'+type_planned+'")]').click()
		
	if execution == "Once":
		if date_from is not None:
			driver.find_element_by_id("text-once_date_from").clear()
			driver.find_element_by_id("text-once_date_from").send_keys(date_from)

		if date_to is not None:
			driver.find_element_by_id("text-once_date_to").clear()
			driver.find_element_by_id("text-once_date_to").send_keys(date_to)

		if hour_from is not None:		
			driver.find_element_by_id("text-once_time_from").clear()
			driver.find_element_by_id("text-once_time_from").send_keys(hour_from)
	
		if hour_to is not None:	
			driver.find_element_by_id("text-once_time_to").clear()
			driver.find_element_by_id("text-once_time_to").send_keys(hour_to)
	
	if execution == "Periodically":			
		Select(driver.find_element_by_id("type_periodicity")).select_by_visible_text(periodicity_type)
		
		if periodicity_type == "Monthly":
				
			Select(driver.find_element_by_id("periodically_day_from")).select_by_visible_text(from_day)		
			Select(driver.find_element_by_id("periodically_day_to")).select_by_visible_text(to_day)
		
		if periodicity_type == "Weekly":
		
			for day in list_days:
				driver.find_element_by_id("checkbox-"+day).click()				
		
		driver.find_element_by_id("text-periodically_time_from").clear()
		driver.find_element_by_id("text-periodically_time_from").send_keys(hour_from)
				
		driver.find_element_by_id("text-periodically_time_to").clear()
		driver.find_element_by_id("text-periodically_time_to").send_keys(hour_to)

	driver.find_element_by_id("submit-crtbutton").click()	
	
	if agent_module_list is not None:
		for agent_name,module_name_list in agent_module_list:
			Select(driver.find_element_by_id("id_agents")).select_by_visible_text(agent_name)
			if type_planned == "Quiet":
				#Quiet types allow to select modules
				time.sleep(3)
				if module_name_list is not []:
					Select(driver.find_element_by_id("module")).deselect_by_visible_text('Any')
				for module_name in module_name_list:
					Select(driver.find_element_by_id("module")).select_by_visible_text(module_name)
				driver.find_element_by_id("submit-add_item").click()
				try:
					alert = driver.switch_to_alert()
					alert.accept()
				except:
					pass

def search_planned_downtime(driver,name,type_planned=None,date_from=None,date_to=None,show_past_downtimes=False):

	#Example format with date_from and date_to: 2016/07/04
	#show_past_downtimes=True for select this option
	#Type can be "Any","Once" or "Periodically", Any is the default
	
	click_menu_element(driver,"Scheduled downtime")

	driver.find_element_by_id("text-search_text").clear()
	driver.find_element_by_id("text-search_text").send_keys(name)
	
	if type_planned != None:
		driver.find_element_by_xpath('//option[contains(.,"'+type_planned+'")]').click()
	
	if date_from != None:
		driver.find_element_by_id("text-date_from").clear()
		driver.find_element_by_id("text-date_from").send_keys(date_from)
	
	if date_to != None:
		driver.find_element_by_id("text-date_to").clear()
		driver.find_element_by_id("text-date_to").send_keys(date_to)
	
	if show_past_downtimes == True:
		driver.find_element_by_id("checkbox-archived").click()		
	
	driver.find_element_by_id("submit-search").click()	

def stop_downtime(driver,name):
	search_planned_downtime(driver,name)	
	driver.find_element_by_xpath('//*[@id="table3-0-stop"]/a/img').click()


def delete_planned_downtime(driver,name):
	search_planned_downtime(driver,name)

	if (u"Not running" not in driver.page_source):
		stop_downtime(driver,name)

	search_planned_downtime(driver,name,show_past_downtimes=True)
	driver.find_element_by_xpath('//*[@id="delete_downtime"]/img').click()
	alert = driver.switch_to_alert()
	alert.accept()
