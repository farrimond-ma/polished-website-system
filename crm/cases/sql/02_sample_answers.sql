-- OPTIONAL demo data: sample Statement-of-Fact answers for case 9676245 (term 1).
-- Only needed to see the Statement of Fact populated in the demo. Safe to skip on live.
-- question_key == the SchemeServe token name (that's how documents find the answer).
DELETE FROM `risk_answer` WHERE `term_id`=1 AND `question_key` IN
 ('Who_Works_In_Your_Business','Years_Experience','TypeOfBusiness_If_NOT_JustMe','Who_Else_2',
  'Activities_DomesticPropertyCleaning','Activities_CommercialPropertyCleaning','Activities_WindowCleaning',
  'Activities_LaundryLinenIroning','Activities_PL_Only_15M_YN','BDSC_Y_N','Claims_Last5Years_YN',
  'WS_WhereIsWorkUndertaken','Above1mYesNo');
INSERT INTO `risk_answer` (`term_id`,`part_name`,`question_key`,`question_text`,`answer_value`) VALUES
 (1,'Cleaning Insurance Quote','Who_Works_In_Your_Business','Who works in your business?','Me and others'),
 (1,'Cleaning Insurance Quote','Years_Experience','Years experience','Over 3 years'),
 (1,'Cleaning Insurance Quote','TypeOfBusiness_If_NOT_JustMe','Type of business','Limited Company'),
 (1,'Cleaning Insurance Quote','Who_Else_2','Who else works for the business','Employed Cleaners'),
 (1,'Activity','Activities_DomesticPropertyCleaning','Internal domestic cleaning','checked'),
 (1,'Activity','Activities_CommercialPropertyCleaning','Internal commercial cleaning','checked'),
 (1,'Activity','Activities_WindowCleaning','Window cleaning','checked'),
 (1,'Activity','Activities_LaundryLinenIroning','Bed linen / ironing','Yes'),
 (1,'Activity','Activities_PL_Only_15M_YN','Work above 15m','No'),
 (1,'Split of Work','Above1mYesNo','Work over 1m?','No'),
 (1,'Employers Liability','BDSC_Y_N','Payments to BFSC?','No'),
 (1,'Claims','Claims_Last5Years_YN','Claims in last 5 years?','No'),
 (1,'Split of Work','WS_WhereIsWorkUndertaken','Where is work undertaken','Inside and Outside Buildings');
