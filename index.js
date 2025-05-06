import { useEffect, useState } from 'react';
import axios from 'axios';

  
export default function Home() {
  const [posts, setPosts] = useState([]);
  const [input, setInput] = useState('');

  useEffect(() => {
    axios.get('http://localhost:5000/posts').then(res => setPosts(res.data));
  }, []);

  const handleSubmit = async () => {
    await axios.post('http://localhost:5000/posts', { content: input });
    const res = await axios.get('http://localhost:5000/posts');
    setPosts(res.data);
    setInput('');
  };

  return (
    <div style={{ padding: 20 }}>
      <h1>Simple Messaging App</h1>
      <textarea value={input} onChange={e => setInput(e.target.value)} />
      <button onClick={handleSubmit}>Post</button>
      <ul>
        {posts.map(p => <li key={p.id}>{p.content}</li>)}
      </ul>
    </div>
  );
}
