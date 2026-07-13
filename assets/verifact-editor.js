((wp)=>{
  const {registerPlugin}=wp.plugins;
  const {PluginDocumentSettingPanel}=wp.editPost;
  const {Button,Notice,Spinner}=wp.components;
  const {createElement:el,useState,useEffect}=wp.element;
  const {select,dispatch,subscribe}=wp.data;
  const api=async(path,options={})=>{const response=await fetch(verifactEditor.restRoot+path,{credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':verifactEditor.nonce},...options});const data=await response.json();if(!response.ok)throw new Error(data.message||data.code||'Request failed');return data;};
  const ReviewPanel=()=>{
    const postId=select('core/editor').getCurrentPostId();
    const [state,setState]=useState({status:'idle',report:null,error:''});
    const poll=async(id)=>{try{const job=await api('/jobs/'+id);if(job.status==='complete'){setState({status:'complete',report:job.result,error:''});return;}if(['failed','cancelled'].includes(job.status)){throw new Error(job.error_message||'Review failed');}setState(s=>({...s,status:job.status}));window.setTimeout(()=>poll(id),1500);}catch(error){setState({status:'failed',report:null,error:error.message});}};
    const start=async()=>{setState({status:'queued',report:null,error:''});try{const job=await api('/posts/'+postId+'/queue',{method:'POST',body:'{}'});poll(job.job_id);}catch(error){setState({status:'failed',report:null,error:error.message});}};
    useEffect(()=>{let previous=select('core/editor').getEditedPostContent();return subscribe(()=>{const current=select('core/editor').getEditedPostContent();if(current!==previous&&state.status==='complete'){previous=current;setState(s=>({...s,status:'changed'}));}});},[]);
    const results=state.report?.results||[];
    return el(PluginDocumentSettingPanel,{name:'verifact-review',title:'VeriFact Review',className:'verifact-review-panel'},
      el('div',{'aria-live':'polite','aria-atomic':'true',className:'verifact-editor-status'},state.status==='queued'||state.status==='running'?el('span',{className:'verifact-editor-wait'},el(Spinner),verifactEditor.labels?.reviewing||' Reviewing factual claims…'):null,state.status==='changed'?el(Notice,{status:'warning',isDismissible:false},'Content changed after review. Queue a revision-aware rescan.'):null,state.error?el(Notice,{status:'error',isDismissible:false},state.error):null),
      el(Button,{variant:'primary',onClick:start,disabled:['queued','running'].includes(state.status),'aria-describedby':'verifact-review-help'},state.status==='changed'?'Review changed claims':'Review this document'),
      el('p',{id:'verifact-review-help',className:'description'},'VeriFact reuses unchanged claim results and sends only changed claims for review.'),
      results.length?el('div',{className:'verifact-editor-results','aria-label':'Fact-check results'},results.map((result,index)=>el('article',{key:index,className:'verifact-editor-result '+result.stance,tabIndex:0},el('h4',null,result.claim),el('p',null,el('strong',null,result.stance.replaceAll('_',' ')),' · ',Math.round(result.confidence*100),'% confidence'),el('p',null,result.method_summary),el('ul',null,(result.evidence||[]).map((source,i)=>el('li',{key:i},el('a',{href:source.url,target:'_blank',rel:'noopener noreferrer'},source.title),' — ',source.publisher)))))):null
    );
  };
  registerPlugin('verifact-review',{render:ReviewPanel});
})(window.wp);
