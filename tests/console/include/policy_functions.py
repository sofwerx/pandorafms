# -*- coding: utf-8 -*-
from common_classes_60 import PandoraWebDriverTestCase
from common_functions_60 import login, click_menu_element, detect_and_pass_all_wizards, logout
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from module_functions import create_module
from selenium.webdriver.support.ui import Select
from selenium.common.exceptions import NoSuchElementException
from selenium.common.exceptions import NoAlertPresentException
import unittest2, time, re


def create_policy(driver,policy_name,group,description=None):

	click_menu_element(driver,"Manage policies")
	driver.find_element_by_id("submit-crt").click()
	driver.find_element_by_id("text-name").send_keys(policy_name)
	driver.find_element_by_xpath('//option[contains(.,"'+group+'")]').click()

	if description!= None:
		driver.find_element_by_id("textarea_description").send_keys(description)
	
	driver.find_element_by_id("submit-crt").click()


def search_policy(driver,policy_name,go_to_policy=True):
	click_menu_element(driver,"Manage policies")
	driver.find_element_by_id("text-text_search").clear()
	driver.find_element_by_id("text-text_search").send_keys(policy_name)
	driver.find_element_by_id("submit-submit").click()
	# If go_to_policy is True, this function enter in options of this policy
	
	if go_to_policy == True:
		driver.find_element_by_xpath('//*[@id="policies_list-0-1"]/span/strong/a/span').click()
	

def add_module_policy(driver,policy_name,module_type,*args,**kwargs):
	search_policy(driver,policy_name,go_to_policy=True)
	driver.find_element_by_xpath('//*[@id="menu_tab"]/ul/li[2]/a/img').click()
	create_module(module_type,*args,**kwargs)


def add_collection_to_policy(driver,policy_name,collection_name):

	search_policy(driver,policy_name,go_to_policy=True)	
	driver.find_element_by_xpath('//*[@id="menu_tab"]/ul/li[6]/a/img').click()	
	
	driver.find_element_by_xpath('//*[@id="main"]/table[2]/tbody/tr/td[2]/form/input[2]').clear()	
	driver.find_element_by_xpath('//*[@id="main"]/table[2]/tbody/tr/td[2]/form/input[2]').send_keys(collection_name)
	driver.find_element_by_xpath('//*[@id="main"]/table[2]/tbody/tr/td[3]/input').click()	
	
	driver.find_element_by_xpath('//*[@id="table3-0-4"]/a/img').click()


def apply_policy_to_agent(driver,policy_name,list_agent):

	#Example by list_agent: list_agent=("PAN_14_1","PAN_14_2")

	search_policy(driver,policy_name,go_to_policy=True)
	driver.find_element_by_xpath('//*[@id="menu_tab"]/ul/li[10]/a/img').click()

	for agent in list_agent:
		Select(driver.find_element_by_id("id_agents")).select_by_visible_text(agent)
	
	driver.find_element_by_xpath('//*[@id="image-add1"]').click()
	alert = driver.switch_to_alert()
	alert.accept()

	driver.find_element_by_xpath('//*[@id="menu_tab"]/ul/li[9]/a/img').click()
	
	driver.find_element_by_xpath('//*[@id="main"]/div[4]/form[2]').click()
	alert = driver.switch_to_alert()
	alert.accept()
