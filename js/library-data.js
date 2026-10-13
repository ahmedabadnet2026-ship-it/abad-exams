/* مكتبة المعهد — إعدادات الكتب (أضف كتاباً جديداً بإضافة عنصر إلى books فقط)
   status: "available" (له ملفات) | "soon" (قريبًا)
   files:  لغات الملفات المتاحة في course-files.html (مثلاً ["en"] أو ["ar","en"])
   ------------------------------------------------------------------
   التحكم بالوصول (مستقبلاً للطلاب المسجّلين فقط):
   ABAD_LIBRARY.getAccess() تُرجع {mode:"open"} حالياً (عرض تجريبي مفتوح).
   لاحقاً استبدلها بدالة تقرأ جلسة الطالب من الخادم وتُرجع:
     {mode:"enrolled", student:{name}, enrolled:["itil5", ...]}
   أو {mode:"guest"} لغير المسجّلين — الصفحة تتعامل مع الحالات الثلاث تلقائياً. */
window.ABAD_LIBRARY={
 vendors:{
  peoplecert:{ar:"PeopleCert",en:"PeopleCert",logo:"assets/logos/peoplecert.svg"},
  comptia:{ar:"CompTIA",en:"CompTIA",logo:"assets/logos/comptia.svg"},
  cisco:{ar:"Cisco",en:"Cisco",logo:"assets/logos/cisco.svg"},
  redhat:{ar:"Red Hat",en:"Red Hat",logo:"assets/logos/redhat.svg"},
  pmi:{ar:"PMI",en:"PMI",logo:"assets/logos/pmi.png"}
 },
 books:[
  {id:"itil5",vendor:"peoplecert",code:"ITIL® V5",title:{ar:"ITIL Foundation (الإصدار 5)",en:"ITIL Foundation (Version 5)"},sub:{ar:"إدارة المنتجات والخدمات الرقمية",en:"Digital Product & Service Management"},
   color:["#3b1f5c","#6a3fa0"],status:"available",files:["en"],href:"course-files.html?c=itil5"},
  {id:"secplus",vendor:"comptia",code:"SY0-701",title:{ar:"CompTIA Security+",en:"CompTIA Security+"},sub:{ar:"أساسيات الأمن السيبراني",en:"Cybersecurity fundamentals"},color:["#7a1c1c","#c0392b"],status:"soon"},
  {id:"netplus",vendor:"comptia",code:"N10-009",title:{ar:"CompTIA Network+",en:"CompTIA Network+"},sub:{ar:"أساسيات الشبكات",en:"Networking fundamentals"},color:["#0e4d64","#1f8aa8"],status:"soon"},
  {id:"aplus",vendor:"comptia",code:"220-1201/1202",title:{ar:"CompTIA A+",en:"CompTIA A+"},sub:{ar:"الدعم الفني",en:"IT support"},color:["#2d5016","#4f8a2a"],status:"soon"},
  {id:"linuxplus",vendor:"comptia",code:"XK0-006",title:{ar:"CompTIA Linux+",en:"CompTIA Linux+"},sub:{ar:"إدارة أنظمة لينكس",en:"Linux administration"},color:["#3a3a3a","#6b6b6b"],status:"soon"},
  {id:"ccna",vendor:"cisco",code:"200-301",title:{ar:"Cisco CCNA",en:"Cisco CCNA"},sub:{ar:"شبكات سيسكو",en:"Cisco networking"},color:["#0b3a5b","#1ba0d7"],status:"soon"},
  {id:"rhcsa",vendor:"redhat",code:"EX200",title:{ar:"Red Hat RHCSA",en:"Red Hat RHCSA"},sub:{ar:"مدير أنظمة Red Hat",en:"Red Hat system administrator"},color:["#5c0b0b","#cc0000"],status:"soon"},
  {id:"pmp",vendor:"pmi",code:"PMP®",title:{ar:"PMP",en:"PMP"},sub:{ar:"إدارة المشاريع الاحترافية",en:"Project Management Professional"},color:["#10293b","#3f86b3"],status:"soon"}
 ],
 getAccess:async function(){return {mode:"open"}},
 canOpen:function(book,acc){if(book.status!=="available")return false;if(!acc||acc.mode==="open")return true;return acc.mode==="enrolled"&&(acc.enrolled||[]).indexOf(book.id)>=0}
};
